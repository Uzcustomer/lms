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
                        <div class="filter-item" style="flex: 1 1 260px; min-width: 240px; max-width: 460px;">
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
                                <option value="auto" selected>Avtomatik (1-3 kurs a,b · 4+ a,b,c)</option>
                                <option value="full">Guruh (to'liq)</option>
                                <option value="ab">a,b guruhchalar</option>
                                <option value="abc">a,b,c guruhchalar</option>
                                <option value="all">Barcha variantlar (Excel)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Me'yorlar (chegaralar) -->
                    <div class="filter-row" style="align-items:flex-end;">
                        <div class="norm-group">
                            <span class="norm-title">Oqim me'yori</span>
                            <div class="norm-inputs">
                                <div><label>max</label><input type="number" id="oqim_max" class="norm-in" value="100" min="1"></div>
                                <div><label>±</label><input type="number" id="oqim_tol" class="norm-in" value="0" min="0"></div>
                            </div>
                        </div>
                        <div class="norm-group">
                            <span class="norm-title">a,b guruhcha</span>
                            <div class="norm-inputs">
                                <div><label>max</label><input type="number" id="ab_max" class="norm-in" value="15" min="1"></div>
                                <div><label>±</label><input type="number" id="ab_tol" class="norm-in" value="0" min="0"></div>
                            </div>
                        </div>
                        <div class="norm-group">
                            <span class="norm-title">a,b,c guruhcha</span>
                            <div class="norm-inputs">
                                <div><label>max</label><input type="number" id="abc_max" class="norm-in" value="10" min="1"></div>
                                <div><label>±</label><input type="number" id="abc_tol" class="norm-in" value="0" min="0"></div>
                            </div>
                        </div>
                        <div class="filter-item" style="min-width: 420px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport(false)" title="Joriy holat (HEMISdagidek)">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                                <button type="button" id="btn-optimize" class="btn-opt" onclick="loadReport(true)" title="Me'yorlar bo'yicha qayta tashkillashtirish">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Optimizatsiya
                                </button>
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                            </div>
                        </div>
                    </div>
                    <p style="margin:2px 2px 0;font-size:11.5px;color:#64748b;">
                        Faqat faol (o'qiyotgan) talabalar hisobga olinadi. <b>Oqim</b> — ma'ruzaga birga boradigan guruhlar
                        (til bo'yicha alohida, talaba soni oqim me'yoridan oshmaydi). <b>Hisoblash</b> — joriy holat (HEMISdagi kichik guruhlar);
                        <b>Optimizatsiya</b> — kichik guruhlarni me'yor bo'yicha qayta hisoblab, ortiqcha guruhlarni qisqartiradi.
                        Har xil tildagi guruhlar bir oqim/guruhga qo'shilmaydi.
                    </p>
                </div>

                <!-- Optimizatsiya rejasi dialogi -->
                <div id="opt-overlay" onclick="if(event.target===this)closeOptDialog()">
                    <div id="opt-dialog">
                        <div class="opt-head">
                            <div>
                                <div class="opt-title">Optimizatsiya rejasi</div>
                                <div class="opt-sub">Guruhlar va oqimlarni kamaytirish uchun quyidagi o'zgarishlar tavsiya etiladi</div>
                            </div>
                            <button type="button" class="opt-x" onclick="closeOptDialog()">&times;</button>
                        </div>
                        <div id="opt-summary" class="opt-summary"></div>
                        <div id="opt-moves" class="opt-moves"></div>
                        <div class="opt-foot">
                            <span style="font-size:11.5px;color:#94a3b8;">Talabalar taxminan teng taqsimlangan holda ko'rsatiladi.</span>
                            <button type="button" class="btn-calc" onclick="closeOptDialog()">Yopish</button>
                        </div>
                    </div>
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

        var lastOptimize = false;

        function getFilters() {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            return {
                education_type: $('#education_type').val() || '',
                faculty: dekanFaculty ? dekanFaculty.value : ($('#faculty').val() || ''),
                variant: $('#variant').val() || 'auto',
                oqim_max: $('#oqim_max').val() || 100,
                oqim_tol: $('#oqim_tol').val() || 0,
                ab_max: $('#ab_max').val() || 15,
                ab_tol: $('#ab_tol').val() || 0,
                abc_max: $('#abc_max').val() || 10,
                abc_tol: $('#abc_tol').val() || 0,
                optimize: lastOptimize ? 1 : 0,
            };
        }

        function loadReport(optimize) {
            lastOptimize = !!optimize;
            var params = getFilters();
            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-calculate, #btn-optimize').prop('disabled', true).css('opacity', '0.6');
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
                    $('#btn-calculate, #btn-optimize').prop('disabled', false).css('opacity', '1');

                    var blocks = res.blocks || [];
                    if (!blocks.length) {
                        $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi. Filtrlarni o'zgartirib ko'ring.");
                        return;
                    }
                    renderReport(res);
                    var modeLabel = res.optimize ? 'Optimizatsiya' : 'Joriy holat';
                    $('#time-badge').text(modeLabel + ' · ' + elapsed + ' soniyada hisoblandi · ' + esc(res.generated_at));
                    if (res.optimize && res.plan) showOptDialog(res.plan);
                    $('#table-area').show();
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                },
                error: function(xhr) {
                    $('#loading-state').hide();
                    $('#btn-calculate, #btn-optimize').prop('disabled', false).css('opacity', '1');
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
                            if (r === 0) html += '<td class="oq-label" rowspan="' + oq.rows.length + '">' + esc(oq.label) + '<span class="oq-sum">' + esc(oq.total) + ' ta</span></td>';
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
            $('#total-badge').text('Jami talaba: ' + grand + ' ta · ' + variantLabel.split('(')[0].trim());
        }

        function statBox(cur, opt, label) {
            var reduce = (cur || 0) - (opt || 0);
            var cls = reduce > 0 ? 'ok' : 'neutral';
            return '<div class="opt-stat ' + cls + '">'
                 + '<div class="opt-stat-num">' + (cur || 0) + ' → ' + (opt || 0) + '</div>'
                 + '<div class="opt-stat-lbl">' + label + (reduce > 0 ? ' <b>(−' + reduce + ')</b>' : '') + '</div></div>';
        }

        function showOptDialog(plan) {
            var s = '';
            s += statBox(plan.cur_base, plan.opt_base, 'Akademik guruhlar');
            s += statBox(plan.cur_subgroups, plan.opt_subgroups, 'Kichik guruhchalar');
            s += statBox(plan.cur_oqim, plan.opt_oqim, 'Oqimlar');
            $('#opt-summary').html(s);

            var moves = plan.moves || [];
            var m = '';
            if (!moves.length) {
                m = '<div class="opt-empty">✓ Joriy taqsimot allaqachon me\'yorga mos — birlashtiriladigan (kam to\'lgan) guruhlar topilmadi.<br>'
                  + '<span style="font-weight:500;font-size:12.5px;color:#64748b;">Ko\'proq birlashtirish uchun me\'yor (max) yoki tolerantlik (±) ni oshiring.</span></div>';
            } else {
                m += '<div class="opt-moves-title">Birlashtirish tavsiyalari (' + moves.length + ' ta joyda guruh kamaytiriladi):</div>';
                for (var i = 0; i < moves.length; i++) {
                    var mv = moves[i];
                    m += '<div class="opt-move">';
                    m += '<div class="opt-move-h"><span class="opt-move-base">' + esc(mv.course) + ' · ' + esc(mv.lang) + ' til</span>'
                       + '<span class="opt-move-meta">' + esc(mv.block) + '</span></div>';
                    m += '<div class="opt-move-b">';
                    m += '<span class="opt-badge cur">Hozir: ' + mv.from + ' guruh</span>';
                    m += '<span class="opt-arrow">→</span>';
                    m += '<span class="opt-badge opt">Bo\'lsin: ' + mv.to + ' guruh (' + esc(mv.sub) + ')</span>';
                    m += '</div>';
                    m += '<div class="opt-move-note"><b>« ' + mv.dropped.join(', ') + ' »</b> guruh(lar)ni bekor qilib, talabalarini '
                       + '<b>' + mv.kept.join(', ') + '</b> guruhlariga taqsimlang (har biri ≈ [' + mv.sizes.join(', ') + '] talaba).</div>';
                    m += '</div>';
                }
            }
            $('#opt-moves').html(m);
            $('#opt-overlay').css('display', 'flex');
        }

        function closeOptDialog() { $('#opt-overlay').hide(); }

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
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; align-items: flex-end; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); transform: translateY(-1px); }
        .btn-opt { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: linear-gradient(135deg, #7c3aed, #a855f7); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(124,58,237,0.3); height: 36px; }
        .btn-opt:hover { background: linear-gradient(135deg, #6d28d9, #7c3aed); transform: translateY(-1px); }

        .norm-group { background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:5px 10px 6px; }
        .norm-title { display:block; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.03em; color:#475569; margin-bottom:3px; }
        .norm-inputs { display:flex; gap:8px; }
        .norm-inputs > div { display:flex; align-items:center; gap:4px; }
        .norm-inputs label { font-size:11px; font-weight:700; color:#64748b; }
        .norm-in { width:60px; height:28px; padding:0 4px; border:1px solid #cbd5e1; border-radius:6px; text-align:center; font-size:12.5px; font-weight:600; color:#1e293b; -moz-appearance:textfield; }
        .norm-in::-webkit-outer-spin-button, .norm-in::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
        .norm-in:focus { outline:none; border-color:#2b5ea7; box-shadow:0 0 0 2px rgba(43,94,167,0.12); }
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
        .oq-sum { display: block; margin-top: 2px; font-size: 10.5px; font-weight: 700; color: #16a34a; }
        .oq-grp { color: #0f172a; white-space: nowrap; }
        .oq-cnt { text-align: center; font-weight: 600; color: #334155; width: 40px; }
        .oq-total td { background: #f1f5f9; font-weight: 800; color: #0f172a; text-align: center; }
        .badge { display: inline-block; font-weight: 600; }

        #opt-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:center; justify-content:center; padding:20px; }
        #opt-dialog { background:#fff; border-radius:14px; width:100%; max-width:760px; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,0.35); overflow:hidden; }
        .opt-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:16px 20px; background:linear-gradient(135deg,#7c3aed,#a855f7); color:#fff; }
        .opt-title { font-size:17px; font-weight:800; }
        .opt-sub { font-size:12px; opacity:0.9; margin-top:2px; }
        .opt-x { background:rgba(255,255,255,0.2); border:none; color:#fff; width:30px; height:30px; border-radius:8px; font-size:20px; line-height:1; cursor:pointer; flex-shrink:0; }
        .opt-x:hover { background:rgba(255,255,255,0.35); }
        .opt-summary { display:flex; gap:12px; padding:16px 20px; flex-wrap:wrap; border-bottom:1px solid #f1f5f9; }
        .opt-stat { flex:1; min-width:150px; border-radius:10px; padding:12px 14px; border:1px solid #e2e8f0; }
        .opt-stat.ok { background:#f0fdf4; border-color:#bbf7d0; }
        .opt-stat.neutral { background:#f8fafc; }
        .opt-stat-num { font-size:22px; font-weight:800; color:#0f172a; }
        .opt-stat.ok .opt-stat-num { color:#16a34a; }
        .opt-stat-lbl { font-size:12px; color:#64748b; font-weight:600; margin-top:2px; }
        .opt-moves { padding:12px 20px; overflow-y:auto; }
        .opt-moves-title { font-size:13px; font-weight:800; color:#334155; margin-bottom:10px; }
        .opt-empty { padding:24px; text-align:center; color:#475569; font-size:13.5px; font-weight:600; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; }
        .opt-move { border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:8px; }
        .opt-move-h { display:flex; align-items:baseline; justify-content:space-between; gap:8px; flex-wrap:wrap; }
        .opt-move-base { font-size:14px; font-weight:800; color:#7c3aed; }
        .opt-move-meta { font-size:11px; color:#94a3b8; }
        .opt-move-b { display:flex; align-items:center; gap:8px; margin-top:6px; flex-wrap:wrap; }
        .opt-badge { font-size:12px; font-weight:700; padding:3px 10px; border-radius:6px; }
        .opt-badge.cur { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
        .opt-badge.opt { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
        .opt-arrow { color:#94a3b8; font-weight:800; }
        .opt-move-note { font-size:12px; color:#475569; margin-top:6px; line-height:1.45; }
        .opt-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 20px; border-top:1px solid #f1f5f9; background:#fbfdff; }
    </style>
</x-app-layout>
