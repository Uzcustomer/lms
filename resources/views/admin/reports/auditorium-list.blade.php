<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Auditoriyalar ro'yxati
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Bino</label>
                            <select id="building" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Auditoriya turi</label>
                            <select id="auditorium_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Holat</label>
                            <select id="status" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="1" selected>Faol</option>
                                <option value="0">Nofaol</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 90px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                            <select id="per_page" class="select2" style="width: 100%;">
                                @foreach([10, 25, 50, 100, 200] as $ps)
                                    <option value="{{ $ps }}" {{ $ps == 50 ? 'selected' : '' }}>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 320px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-sync" class="btn-sync-lg" onclick="syncAuditoriums()">
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Sinxronlash
                                </button>
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(1)">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Ko'rsatish
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Ko'rsatish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Auditoriyalar ro'yxati shu yerda ko'rsatiladi</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Yuklanmoqda...</p>
                        <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Iltimos kutib turing</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#f0f9ff;border-bottom:1px solid #bae6fd;display:flex;align-items:center;gap:12px;">
                            <span id="total-badge" class="badge" style="background:#0284c7;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div style="max-height:calc(100vh - 300px);overflow-y:auto;overflow-x:auto;">
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th><a href="#" class="sort-link" data-sort="code">Kod <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="name">Nomi <span class="sort-icon active">&#9650;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="volume">Sig'imi <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="building_name">Bino <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="auditorium_type_name">Turi <span class="sort-icon">&#9650;&#9660;</span></a></th>
                                        <th><a href="#" class="sort-link" data-sort="active">Holat <span class="sort-icon">&#9650;&#9660;</span></a></th>
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
        let currentSort = 'name';
        let currentDirection = 'asc';
        let currentPage = 1;

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
                building_id: $('#building').val() || '',
                auditorium_type_code: $('#auditorium_type').val() || '',
                status: $('#status').val() || '',
                per_page: $('#per_page').val() || 50,
                sort: currentSort,
                direction: currentDirection,
            };
        }

        function syncAuditoriums() {
            var btn = document.getElementById('btn-sync');
            btn.disabled = true;
            btn.style.opacity = '0.5';
            var origText = btn.innerHTML;
            btn.innerHTML = '<svg style="width:15px;height:15px;animation:spin 0.8s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Sinxronlanmoqda...';

            $.ajax({
                url: '{{ route("admin.reports.auditorium-list.sync") }}',
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                timeout: 60000,
                success: function(res) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.innerHTML = origText;
                    loadFilters();
                    loadReport(1);
                    alert(res.message || 'Sinxronlashtirildi');
                },
                error: function(xhr) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.innerHTML = origText;
                    var errMsg = 'Xatolik yuz berdi';
                    try { errMsg = JSON.parse(xhr.responseText).error || errMsg; } catch(e) {}
                    alert(errMsg);
                }
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
                url: '{{ route("admin.reports.auditorium-list.data") }}',
                type: 'GET',
                data: params,
                timeout: 30000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                    if (!res.data || res.data.length === 0) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                        $('#table-area').hide();
                        $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                        return;
                    }

                    $('#total-badge').text('Jami: ' + res.total + ' ta auditoriya');
                    $('#time-badge').text(elapsed + ' soniyada yuklandi');
                    renderTable(res.data);
                    renderPagination(res);
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg += ' (' + xhr.responseJSON.error + ')';
                    }
                    $('#empty-state').show().find('p:first').text(msg);
                }
            });
        }

        function esc(s) { return $('<span>').text(s || '-').html(); }

        function renderTable(data) {
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var r = data[i];
                html += '<tr class="journal-row">';
                html += '<td class="td-num">' + r.row_num + '</td>';
                html += '<td><span class="badge badge-code">' + esc(r.code) + '</span></td>';
                html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.name) + '</span></td>';
                html += '<td style="text-align:center;"><span class="badge badge-volume">' + r.volume + '</span></td>';
                html += '<td><span class="text-cell text-emerald">' + esc(r.building_name) + '</span></td>';
                html += '<td><span class="badge badge-violet">' + esc(r.auditorium_type_name) + '</span></td>';
                html += '<td style="text-align:center;">';
                if (r.active) {
                    html += '<span class="badge badge-active">Faol</span>';
                } else {
                    html += '<span class="badge badge-inactive">Nofaol</span>';
                }
                html += '</td>';
                html += '</tr>';
            }
            $('#table-body').html(html);
        }

        function downloadExcel() {
            var params = getFilters();
            params.export = 'excel';
            var query = $.param(params);
            window.location.href = '{{ route("admin.reports.auditorium-list.data") }}?' + query;
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

        function loadFilters() {
            // Binolar
            var buildingVal = $('#building').val();
            $('#building').empty().append('<option value="">Barchasi</option>');
            $.get('{{ route("admin.reports.auditorium-list.buildings") }}', {}, function(d) {
                $.each(d, function(k, v) {
                    $('#building').append('<option value="' + k + '">' + v + '</option>');
                });
                if (buildingVal) $('#building').val(buildingVal).trigger('change.select2');
            });

            // Auditoriya turlari
            var typeVal = $('#auditorium_type').val();
            $('#auditorium_type').empty().append('<option value="">Barchasi</option>');
            $.get('{{ route("admin.reports.auditorium-list.types") }}', {}, function(d) {
                $.each(d, function(k, v) {
                    $('#auditorium_type').append('<option value="' + k + '">' + v + '</option>');
                });
                if (typeVal) $('#auditorium_type').val(typeVal).trigger('change.select2');
            });
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

            // Select2 init
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Init filters
            loadFilters();
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

        .btn-sync-lg { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #6366f1, #818cf8); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(99,102,241,0.3); height: 36px; }
        .btn-sync-lg:hover:not(:disabled) { background: linear-gradient(135deg, #4f46e5, #6366f1); box-shadow: 0 4px 10px rgba(99,102,241,0.4); transform: translateY(-1px); }
        .btn-sync-lg:disabled { cursor: not-allowed; opacity: 0.5; }

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

        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { padding: 14px 12px 14px 16px; width: 44px; }
        .sort-link { display: inline-flex; align-items: center; gap: 4px; color: #334155; text-decoration: none; cursor: pointer; }
        .sort-link:hover { opacity: 0.75; }
        .sort-icon { font-size: 8px; opacity: 0.4; }
        .sort-icon.active { font-size: 11px; opacity: 1; color: #0284c7; }

        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #f0f9ff !important; box-shadow: inset 4px 0 0 #0284c7; }
        .journal-table td { padding: 10px 12px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #0284c7; font-size: 13px; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-code { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; font-family: monospace; font-size: 12px; }
        .badge-volume { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 4px 12px; font-size: 12.5px; font-weight: 700; }
        .badge-active { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .badge-inactive { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }

        .pg-btn { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: all 0.15s; }
        .pg-btn:hover { background: #f0f9ff; border-color: #0284c7; color: #0284c7; }
        .pg-active { background: linear-gradient(135deg, #0284c7, #0ea5e9) !important; color: #fff !important; border-color: #0284c7 !important; }
    </style>
</x-app-layout>
