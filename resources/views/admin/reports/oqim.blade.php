<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Oqim hisoboti — talabalarni oqim va guruhlarga taqsimlash
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 170px;">
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
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width: 100%;" {{ isset($dekanFacultyId) && $dekanFacultyId ? 'disabled' : '' }}>
                                @if(isset($dekanFacultyId) && $dekanFacultyId)
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" selected>{{ $faculty->name }}</option>
                                    @endforeach
                                @else
                                    <option value="">Barchasi</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @if(isset($dekanFacultyId) && $dekanFacultyId)
                                <input type="hidden" id="dekan_faculty_id" value="{{ $dekanFacultyId }}">
                            @endif
                        </div>
                        <div class="filter-item" style="min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Variant (bo'linish)</label>
                            <select id="variant" class="select2" style="width: 100%;">
                                <option value="1" selected>Guruh (to'liq)</option>
                                <option value="2">A.B (2 ga bo'lib)</option>
                                <option value="3">A.B.C (3 ga bo'lib)</option>
                                <option value="all">Barcha variantlar (Excel)</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Oqimdagi guruh</label>
                            <select id="oqim_size" class="select2" style="width: 100%;">
                                @foreach([2, 3, 4] as $sz)
                                    <option value="{{ $sz }}" {{ $sz == 2 ? 'selected' : '' }}>{{ $sz }} ta guruh</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 300px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport()">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel yuklash
                                </button>
                            </div>
                        </div>
                    </div>
                    <p style="margin:2px 2px 0;font-size:11.5px;color:#64748b;">
                        Faqat faol (o'qiyotgan) talabalar hisobga olinadi. Oqimlar avtomatik shakllantiriladi:
                        guruhlar raqami bo'yicha har <span id="hint-size">2</span> ta guruh bitta oqim, rus/ingliz guruhlari alohida oqimga birlashadi.
                    </p>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2.83-4M7 12a3 3 0 10-2.83-4"/>
                        </svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:10px 20px;background:#eff6ff;border-bottom:1px solid #bfdbfe;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span id="total-badge" class="badge" style="background:#2b5ea7;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            <span id="time-badge" style="font-size:12px;color:#64748b;"></span>
                        </div>
                        <div id="report-body" style="padding:16px 20px;max-height:calc(100vh - 300px);overflow:auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function esc(s) { return $('<span>').text(s === null || s === undefined ? '' : s).html(); }

        function getFilters() {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            return {
                education_type: $('#education_type').val() || '',
                faculty: dekanFaculty ? dekanFaculty.value : ($('#faculty').val() || ''),
                variant: $('#variant').val() || '1',
                oqim_size: $('#oqim_size').val() || 2,
            };
        }

        function loadReport() {
            var params = getFilters();
            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');
            $('#btn-excel').prop('disabled', true).css('opacity', '0.5');

            var startTime = performance.now();
            $.ajax({
                url: '{{ route("admin.reports.oqim.data") }}',
                type: 'GET',
                data: params,
                timeout: 120000,
                success: function(res) {
                    var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                    var blocks = res.blocks || [];
                    if (!blocks.length) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi. Filtrlarni o'zgartirib ko'ring.");
                        return;
                    }
                    renderReport(res);
                    $('#time-badge').text(elapsed + ' soniyada hisoblandi · ' + esc(res.generated_at));
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                    var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                    if (xhr.responseJSON && xhr.responseJSON.error) msg += ' (' + xhr.responseJSON.error + ')';
                    else if (xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                    $('#empty-state').show().find('p:first').text(msg);
                }
            });
        }

        function renderReport(res) {
            var blocks = res.blocks || [];
            var grand = 0;
            var html = '';
            for (var b = 0; b < blocks.length; b++) {
                var block = blocks[b];
                html += '<div class="oqim-block">';
                html += '<div class="oqim-block-title">' + esc(block.title) + '</div>';
                html += '<div class="oqim-courses">';
                for (var c = 0; c < block.courses.length; c++) {
                    var course = block.courses[c];
                    grand += course.total;
                    html += '<div class="oqim-course">';
                    html += '<table class="oqim-table"><thead><tr><th colspan="3">' + esc(course.level_name) + '</th></tr></thead><tbody>';
                    for (var o = 0; o < course.oqims.length; o++) {
                        var oq = course.oqims[o];
                        for (var r = 0; r < oq.rows.length; r++) {
                            html += '<tr>';
                            if (r === 0) html += '<td class="oq-label" rowspan="' + oq.rows.length + '">' + esc(oq.label) + '</td>';
                            html += '<td class="oq-grp">' + esc(oq.rows[r].name) + '</td>';
                            html += '<td class="oq-cnt">' + esc(oq.rows[r].count) + '</td>';
                            html += '</tr>';
                        }
                    }
                    html += '<tr class="oq-total"><td colspan="2">Jami</td><td class="oq-cnt">' + esc(course.total) + '</td></tr>';
                    html += '</tbody></table>';
                    html += '</div>';
                }
                html += '</div></div>';
            }
            $('#report-body').html(html);
            var variantLabel = $('#variant option:selected').text();
            $('#total-badge').text('Jami talaba: ' + grand + ' ta · ' + variantLabel);
        }

        function downloadExcel() {
            var params = getFilters();
            // Excel har doim barcha variantlarni chiqarishi uchun tanlanganini yuboramiz
            var query = $.param(params);
            window.location.href = '{{ route("admin.reports.oqim.export") }}?' + query;
        }

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', placeholder: $(this).find('option:first').text() });
            });
            $('#oqim_size').on('change', function() { $('#hint-size').text($(this).val()); });
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; align-items: flex-end; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); transform: translateY(-1px); }
        .btn-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }

        .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; color: #1e293b; font-size: 0.8rem; font-weight: 500; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; }

        .oqim-block { margin-bottom: 26px; }
        .oqim-block-title { font-size: 14px; font-weight: 800; color: #0f172a; background: linear-gradient(135deg, #e8edf5, #dbe4ef); padding: 8px 12px; border-radius: 8px 8px 0 0; border: 1px solid #cbd5e1; }
        .oqim-courses { display: flex; gap: 10px; flex-wrap: nowrap; overflow-x: auto; padding: 10px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; background: #fbfdff; }
        .oqim-course { flex: 0 0 auto; }
        .oqim-table { border-collapse: collapse; font-size: 12px; min-width: 190px; }
        .oqim-table th { background: linear-gradient(135deg, #dbe4ef, #cbd7e8); color: #1e3a5f; font-weight: 700; padding: 6px 8px; text-align: center; border: 1px solid #b8c6dc; font-size: 12px; }
        .oqim-table td { border: 1px solid #e2e8f0; padding: 4px 8px; }
        .oq-label { text-align: center; font-weight: 700; color: #2b5ea7; background: #f0f6ff; white-space: nowrap; }
        .oq-grp { color: #0f172a; white-space: nowrap; }
        .oq-cnt { text-align: center; font-weight: 600; color: #334155; width: 40px; }
        .oq-total td { background: #f1f5f9; font-weight: 800; color: #0f172a; text-align: center; }
        .badge { display: inline-block; font-weight: 600; }
    </style>
</x-app-layout>
