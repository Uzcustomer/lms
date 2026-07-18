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
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Ta'lim</label>
                            <select id="talim" class="select2" style="width: 100%;">
                                <option value="all" selected>Barchasi</option>
                                <option value="oddiy">Kunduzgi (oddiy)</option>
                                <option value="qoshma">Qo'shma ta'lim</option>
                            </select>
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
                        <div class="norm-group" style="align-self:stretch;display:flex;align-items:center;">
                            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:12px;font-weight:700;color:#334155;" title="Fakultetlar ALOHIDA qoladi (har birining o'z dekani bor). Bir fakultetning kam to'lgan oqimi qo'shni fakultet (masalan 1↔2-son davolash) oqimiga — joy bo'lsa — ko'chiriladi, shunda oqimlar soni kamayadi. Faqat optimizatsiyalangan holatga qo'llanadi.">
                                <input type="checkbox" id="merge_faculties" style="width:16px;height:16px;cursor:pointer;">
                                Fakultetlararo oqim optimizatsiyasi<br><span style="font-weight:500;font-size:10.5px;color:#94a3b8;">(kam to'lgan oqimni qo'shni fakultetga ko'chirish)</span>
                            </label>
                        </div>
                        <div class="filter-item" style="min-width: 420px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="loadReport()" title="Joriy holat va optimizatsiya taklifini hisoblash">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <a href="{{ route('admin.reports.oqim.overrides') }}" class="btn-fix" title="Aralash tilli / xato guruhlarni qo'lda to'g'rilash">
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Guruh tuzatish
                                </a>
                            </div>
                        </div>
                    </div>
                    <p style="margin:2px 2px 0;font-size:11.5px;color:#64748b;">
                        Faqat faol (o'qiyotgan) talabalar hisobga olinadi. <b>Oqim</b> — ma'ruzaga birga boradigan guruhlar
                        (til bo'yicha alohida, talaba soni oqim me'yoridan oshmaydi). <b>Joriy holat</b> — HEMISdagidek, tasdiqlanadigan holat (o'zgarmaydi);
                        <b>Taklif etilayotgan o'zgartirish</b> — nimani nimaga o'zgartirish (kamayadigan guruh/oqimlar); <b>Optimizatsiyadan keyingi holat</b> — o'zgarishlardan keyingi to'liq holat (tasdiqlangach joriy holatga aylanadi).
                        <b>Fakultetlararo oqim optimizatsiyasi</b> yoqilsa — fakultetlar alohida qoladi, faqat bir fakultetning kam to'lgan oqimi qo'shni fakultet oqimiga ko'chiriladi (mehmon guruhlar belgilanadi).
                        Har xil tildagi guruhlar bir oqim/guruhga qo'shilmaydi.
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
                        <div style="padding:8px 20px 0;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <button type="button" class="oq-tab active" data-tab="joriy" onclick="switchTab('joriy')" title="HEMISdagi haqiqiy, tasdiqlanadigan holat — optimizatsiya bunga ta'sir qilmaydi">Joriy holat</button>
                            <button type="button" class="oq-tab" data-tab="taklif" onclick="switchTab('taklif')" title="Nimani nimaga o'zgartirish taklifi — kamayadigan guruh/oqimlar">
                                Taklif etilayotgan o'zgartirish <span id="opt-tab-badge" class="opt-tab-badge" style="display:none;"></span>
                            </button>
                            <button type="button" class="oq-tab" data-tab="after" onclick="switchTab('after')" title="Optimizatsiya qo'llangandan keyingi to'liq holat">
                                Optimizatsiyadan keyingi holat
                            </button>
                            <span id="time-badge" style="font-size:12px;color:#94a3b8;margin-left:auto;"></span>
                        </div>

                        <!-- JORIY tab -->
                        <div id="tab-joriy">
                            <div style="padding:8px 20px;background:#eff6ff;border-bottom:1px solid #bfdbfe;">
                                <span id="total-badge" class="badge" style="background:#2b5ea7;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            </div>
                            <div id="report-body" style="padding:16px 20px;max-height:calc(100vh - 340px);overflow:auto;"></div>
                        </div>

                        <!-- TAKLIF ETILAYOTGAN O'ZGARTIRISH tab (solishtirma) -->
                        <div id="tab-taklif" style="display:none;">
                            <div id="opt-summary" class="opt-summary"></div>
                            <div id="opt-compare" style="padding:4px 20px 16px;max-height:calc(100vh - 360px);overflow:auto;"></div>
                        </div>

                        <!-- OPTIMIZATSIYADAN KEYINGI HOLAT tab (to'liq layout) -->
                        <div id="tab-after" style="display:none;">
                            <div style="padding:8px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <span id="after-total-badge" class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                                <span id="after-merge-note" style="display:none;font-size:12px;font-weight:600;color:#166534;">Fakultetlar alohida — kam to'lgan oqimlar qo'shni fakultet oqimiga ko'chirildi (mehmon guruhlar belgilangan).</span>
                                <span style="font-size:11.5px;color:#94a3b8;margin-left:auto;">Bu holat — tasdiqlangach joriy holatga aylanadi.</span>
                            </div>
                            <div id="opt-body" style="padding:16px 20px;max-height:calc(100vh - 380px);overflow:auto;"></div>
                        </div>
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

        var activeTab = 'joriy';

        function getFilters(optimize) {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            var f = {
                education_type: $('#education_type').val() || '',
                faculty: dekanFaculty ? dekanFaculty.value : ($('#faculty').val() || ''),
                talim: $('#talim').val() || 'all',
                variant: $('#variant').val() || 'auto',
                oqim_max: $('#oqim_max').val() || 100,
                oqim_tol: $('#oqim_tol').val() || 0,
                ab_max: $('#ab_max').val() || 15,
                ab_tol: $('#ab_tol').val() || 0,
                abc_max: $('#abc_max').val() || 10,
                abc_tol: $('#abc_tol').val() || 0,
                optimize: optimize ? 1 : 0,
            };
            // Fakultetlararo optimizatsiya FAQAT optimizatsiyalangan holatga qo'llanadi —
            // joriy (tasdiqlangan) holat hech qachon o'zgarmaydi.
            if (optimize) {
                f.merge_faculties = $('#merge_faculties').is(':checked') ? 1 : 0;
            }
            return f;
        }

        function switchTab(tab) {
            activeTab = tab;
            $('.oq-tab').removeClass('active');
            $('.oq-tab[data-tab="' + tab + '"]').addClass('active');
            $('#tab-joriy').toggle(tab === 'joriy');
            $('#tab-taklif').toggle(tab === 'taklif');
            $('#tab-after').toggle(tab === 'after');
        }

        function loadReport() {
            var url = '{{ route("admin.reports.oqim.data") }}';
            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');
            $('#btn-excel').prop('disabled', true).css('opacity', '0.5');

            var startTime = performance.now();
            // Ikkala holatni parallel yuklaymiz: joriy va optimizatsiya
            $.when(
                $.get(url, getFilters(false)),
                $.get(url, getFilters(true))
            ).done(function(r0, r1) {
                var joriy = r0[0], opt = r1[0];
                var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                $('#loading-state').hide();
                $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                if (!joriy.blocks || !joriy.blocks.length) {
                    $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi. Filtrlarni o'zgartirib ko'ring.");
                    return;
                }
                renderReport(joriy);
                renderOptimized(opt);
                renderComparison(opt.plan);
                $('#time-badge').text(elapsed + ' soniyada hisoblandi · ' + esc(joriy.generated_at));
                switchTab('joriy');
                $('#table-area').show();
                $('#btn-excel').prop('disabled', false).css('opacity', '1');
            }).fail(function(xhr) {
                $('#loading-state').hide();
                $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                if (xhr.responseJSON && xhr.responseJSON.error) msg += ' (' + xhr.responseJSON.error + ')';
                else if (xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                $('#empty-state').show().find('p:first').text(msg);
            });
        }

        // Bloklar layoutini chizadi (joriy va optimizatsiyalangan holat uchun umumiy).
        // Talabalarning umumiy sonini qaytaradi.
        function renderBlocks(blocks, bodySel) {
            blocks = blocks || [];
            var grand = 0;
            var html = '<div class="lang-legend">Til: <span class="ll lang-uz">o\'z</span> <span class="ll lang-rus">rus</span> <span class="ll lang-ing">ing</span></div>';
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
                        var lc = 'lang-' + (oq.lang || 'uz');
                        for (var r = 0; r < oq.rows.length; r++) {
                            var first = (r === 0), last = (r === oq.rows.length - 1);
                            var row = oq.rows[r];
                            html += '<tr class="' + lc + (first ? ' oq-first' : '') + (last ? ' oq-last' : '') + (row.visitor ? ' oq-visitor' : '') + '">';
                            if (first) html += '<td class="oq-label" rowspan="' + oq.rows.length + '">' + esc(oq.label) + '<span class="oq-sum">' + esc(oq.total) + ' ta</span>' + (oq.has_visitor ? '<span class="oq-mix">fakultetlararo</span>' : '') + '</td>';
                            html += '<td class="oq-grp">' + esc(row.name)
                                 + (row.visitor ? ' <span class="oq-from">← ' + esc(row.from) + '</span>' : '') + '</td>';
                            html += '<td class="oq-cnt">' + esc(row.count) + '</td>';
                            html += '</tr>';
                        }
                    }
                    html += '<tr class="oq-total"><td colspan="2">Jami</td><td class="oq-cnt">' + esc(course.total) + '</td></tr>';
                    html += '</tbody></table>';
                    html += '</div>';
                }
                html += '</div></div>';
            }
            $(bodySel).html(html);
            return grand;
        }

        function renderReport(res) {
            var grand = renderBlocks(res.blocks, '#report-body');
            var variantLabel = $('#variant option:selected').text();
            $('#total-badge').text('Jami talaba: ' + grand + ' ta · ' + variantLabel.split('(')[0].trim());
        }

        // Optimizatsiyadan keyingi holat — to'liq layout (joriy kabi, lekin kam to'lgan guruhlar
        // birlashtirilgan; fakultetlararo yoqilgan bo'lsa kam to'lgan oqimlar qo'shni fakultetga ko'chirilgan).
        function renderOptimized(res) {
            var grand = renderBlocks(res.blocks, '#opt-body');
            var variantLabel = $('#variant option:selected').text();
            $('#after-total-badge').text('Jami talaba: ' + grand + ' ta · ' + variantLabel.split('(')[0].trim());
            var hasX = res.plan && res.plan.xmoves && res.plan.xmoves.length;
            $('#after-merge-note').toggle(!!($('#merge_faculties').is(':checked') && hasX));
        }

        function statBox(cur, opt, label) {
            var reduce = (cur || 0) - (opt || 0);
            var cls = reduce > 0 ? 'ok' : 'neutral';
            return '<div class="opt-stat ' + cls + '">'
                 + '<div class="opt-stat-num">' + (cur || 0) + ' → ' + (opt || 0) + '</div>'
                 + '<div class="opt-stat-lbl">' + label + (reduce > 0 ? ' <b>(−' + reduce + ')</b>' : '') + '</div></div>';
        }

        function renderComparison(plan) {
            plan = plan || {};
            var s = '';
            s += statBox(plan.cur_base, plan.opt_base, 'Akademik guruhlar');
            s += statBox(plan.cur_subgroups, plan.opt_subgroups, 'Kichik guruhchalar');
            s += statBox(plan.cur_oqim, plan.opt_oqim, 'Oqimlar');
            $('#opt-summary').html(s);

            var moves = plan.moves || [];
            var xmoves = plan.xmoves || [];
            var reduceSub = (plan.cur_subgroups || 0) - (plan.opt_subgroups || 0);
            var reduceOqim = (plan.cur_oqim || 0) - (plan.opt_oqim || 0);
            var badgeN = reduceOqim > 0 ? reduceOqim : reduceSub;
            $('#opt-tab-badge').toggle(badgeN > 0).text(badgeN > 0 ? '−' + badgeN : '');

            var m = '';

            // Fakultetlararo oqim ko'chirishlar (bosh o'zgarish — fakultetlar alohida qoladi)
            if (xmoves.length) {
                m += '<div class="opt-moves-title">Fakultetlararo oqim ko\'chirish — ' + xmoves.length + ' ta kam to\'lgan oqim qo\'shni fakultetga ko\'chiriladi (jami −' + xmoves.length + ' oqim):</div>';
                for (var xi = 0; xi < xmoves.length; xi++) {
                    var xm = xmoves[xi];
                    var gl = (xm.moved || []).map(function(g){ return esc(g.name) + ' (' + esc(g.count) + ')'; }).join(', ');
                    m += '<div class="cmp-card">';
                    m += '<div class="cmp-head"><span class="cmp-title">' + esc(xm.course) + ' · ' + esc(xm.lang) + ' til</span>'
                       + '<span class="cmp-count">' + esc(xm.from_fac) + ' → ' + esc(xm.to_fac) + '</span></div>';
                    m += '<div class="xmove-body"><b>' + esc(xm.from_fac) + '</b> dagi ' + gl + ' <b>[' + esc(xm.moved_total) + ' ta]</b> '
                       + '→ <b>' + esc(xm.to_fac) + '</b> oqimiga qo\'shiladi (' + esc(xm.to_before) + ' → <b>' + esc(xm.to_after) + '</b> ta).</div>';
                    m += '</div>';
                }
            }

            if (!moves.length && !xmoves.length) {
                m = '<div class="opt-empty">✓ Joriy taqsimot allaqachon me\'yorga mos — birlashtiriladigan (kam to\'lgan) guruh/oqim topilmadi.<br>'
                  + '<span style="font-weight:500;font-size:12.5px;color:#64748b;">Ko\'proq zichlash uchun me\'yor (max) yoki tolerantlik (±) ni oshiring; fakultetlararo ko\'chirish uchun tegishli katakchani belgilang.</span></div>';
            } else if (moves.length) {
                m += '<div class="opt-moves-title">Kichik guruhlarni zichlash — ' + moves.length + ' ta joyda kichik guruh kamaytiriladi (qizil = o\'chiriladi):</div>';
                for (var i = 0; i < moves.length; i++) {
                    var mv = moves[i];
                    var cur = mv.cur_subs || [], nw = mv.new_subs || [];

                    m += '<div class="cmp-card">';
                    m += '<div class="cmp-head"><span class="cmp-title">' + esc(mv.course) + ' · ' + esc(mv.lang) + ' til</span>'
                       + '<span class="cmp-meta">' + esc(mv.block) + '</span>'
                       + '<span class="cmp-count">' + mv.cur_sub_n + ' → ' + mv.new_sub_n + ' guruhcha · ' + mv.from + '→' + mv.to + ' guruh</span></div>';

                    m += '<table class="cmp-table"><thead><tr>'
                       + '<th>Joriy versiya (' + mv.cur_sub_n + ' guruhcha)</th><th style="width:32px;"></th><th>Yangi versiya (' + mv.new_sub_n + ' guruhcha)</th>'
                       + '</tr></thead><tbody>';

                    var rows = Math.max(cur.length, nw.length);
                    for (var r = 0; r < rows; r++) {
                        m += '<tr>';
                        // Joriy — yangidan ortiqcha qatorlar (oxiridan) o'chiriladi
                        if (r < cur.length) {
                            var isDrop = r >= nw.length;
                            m += '<td class="cmp-cell ' + (isDrop ? 'cmp-drop' : '') + '">'
                               + esc(cur[r].name) + ' <span class="cmp-num">' + esc(cur[r].count) + ' ta</span>'
                               + (isDrop ? ' <span class="cmp-x">o\'chiriladi</span>' : '') + '</td>';
                        } else { m += '<td></td>'; }
                        m += '<td class="cmp-arrow">' + (r === 0 ? '→' : '') + '</td>';
                        // Yangi
                        if (r < nw.length) {
                            m += '<td class="cmp-cell cmp-new">' + esc(nw[r].name)
                               + ' <span class="cmp-num">' + esc(nw[r].count) + ' ta</span></td>';
                        } else { m += '<td></td>'; }
                        m += '</tr>';
                    }
                    m += '</tbody></table>';
                    var elim = cur.length - nw.length;
                    if (elim > 0) {
                        m += '<div class="cmp-note">Oxirgi <b>' + elim + ' ta</b> kichik guruh o\'chirilib, talabalari yuqoridagi guruhchalarga teng taqsimlanadi'
                           + ((mv.dropped && mv.dropped.length) ? ' (guruh o\'chadi: <b>' + mv.dropped.join(', ') + '</b>)' : '') + '.</div>';
                    }
                    m += '</div>';
                }
            }
            $('#opt-compare').html(m);
        }

        function downloadExcel() {
            // Faol vkladka bo'yicha Excelга yuklaymiz: "joriy" — joriy holat; aks holda
            // (taklif / optimizatsiyadan keyingi holat) — optimizatsiyalangan holat.
            var params = getFilters(activeTab !== 'joriy');
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
        .btn-fix { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #fff; color: #b45309; border: 1px solid #fcd34d; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; height: 36px; }
        .btn-fix:hover { background: #fffbeb; border-color: #f59e0b; }

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

        /* Oqimni o'qitish tili bo'yicha ranglash */
        .oqim-table td.oq-grp { border-left: 3px solid transparent; }
        .lang-uz  td.oq-grp { border-left-color:#3b82f6; }
        .lang-rus td.oq-grp { border-left-color:#f43f5e; }
        .lang-ing td.oq-grp { border-left-color:#8b5cf6; }
        .lang-uz  .oq-label { color:#1d4ed8; background:#eff6ff; }
        .lang-rus .oq-label { color:#be123c; background:#fff1f2; }
        .lang-ing .oq-label { color:#6d28d9; background:#f5f3ff; }
        .lang-rus td.oq-grp, .lang-rus td.oq-cnt { background:#fffafa; }
        .lang-ing td.oq-grp, .lang-ing td.oq-cnt { background:#fbfaff; }
        tr.oq-first td { border-top:2px solid #cbd5e1; }

        .lang-legend { font-size:12px; font-weight:700; color:#64748b; margin-bottom:10px; display:flex; align-items:center; gap:8px; }
        .lang-legend .ll { padding:2px 12px; border-radius:999px; font-weight:800; border-left:3px solid; }
        .lang-legend .ll.lang-uz  { color:#1d4ed8; background:#eff6ff; border-left-color:#3b82f6; }
        .lang-legend .ll.lang-rus { color:#be123c; background:#fff1f2; border-left-color:#f43f5e; }
        .lang-legend .ll.lang-ing { color:#6d28d9; background:#f5f3ff; border-left-color:#8b5cf6; }

        /* Fakultetlararo ko'chirilgan (mehmon) guruhlar — "Optimizatsiyadan keyingi holat" da */
        tr.oq-visitor td.oq-grp { background:#fff7ed; }
        tr.oq-visitor td.oq-cnt { background:#fff7ed; }
        .oq-from { display:inline-block; font-size:10px; font-weight:800; color:#c2410c; background:#ffedd5; border-radius:999px; padding:0 7px; margin-left:4px; }
        .oq-mix { display:block; margin-top:2px; font-size:9.5px; font-weight:800; color:#c2410c; }
        .xmove-body { padding:8px 12px; font-size:13px; color:#334155; line-height:1.5; background:#fffbeb; }

        #opt-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:center; justify-content:center; padding:20px; }
        #opt-dialog { background:#fff; border-radius:14px; width:100%; max-width:760px; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,0.35); overflow:hidden; }
        .opt-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:16px 20px; background:linear-gradient(135deg,#7c3aed,#a855f7); color:#fff; }
        .opt-title { font-size:17px; font-weight:800; }
        .opt-sub { font-size:12px; opacity:0.9; margin-top:2px; }
        .opt-x { background:rgba(255,255,255,0.2); border:none; color:#fff; width:30px; height:30px; border-radius:8px; font-size:20px; line-height:1; cursor:pointer; flex-shrink:0; }
        .opt-x:hover { background:rgba(255,255,255,0.35); }
        .oq-tab { padding:8px 16px; border:none; background:transparent; border-bottom:3px solid transparent; font-size:13px; font-weight:700; color:#64748b; cursor:pointer; margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px; }
        .oq-tab:hover { color:#2b5ea7; }
        .oq-tab.active { color:#2b5ea7; border-bottom-color:#2b5ea7; }
        .opt-tab-badge { background:#16a34a; color:#fff; font-size:10.5px; font-weight:800; padding:1px 7px; border-radius:999px; }

        .cmp-card { border:1px solid #e2e8f0; border-radius:10px; margin-bottom:12px; overflow:hidden; }
        .cmp-head { display:flex; align-items:baseline; gap:12px; flex-wrap:wrap; padding:8px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
        .cmp-title { font-size:14px; font-weight:800; color:#7c3aed; }
        .cmp-meta { font-size:11px; color:#94a3b8; }
        .cmp-count { margin-left:auto; font-size:12px; font-weight:800; color:#16a34a; }
        .cmp-table { width:100%; border-collapse:collapse; font-size:13px; }
        .cmp-table th { text-align:left; padding:6px 12px; font-size:11px; text-transform:uppercase; font-weight:700; color:#64748b; background:#fff; border-bottom:1px solid #f1f5f9; }
        .cmp-table td { padding:5px 12px; border-bottom:1px solid #f8fafc; vertical-align:middle; }
        .cmp-cell { font-weight:700; color:#0f172a; }
        .cmp-num { font-weight:600; color:#64748b; font-size:12px; }
        .cmp-drop { color:#dc2626; text-decoration:line-through; background:#fef2f2; border-radius:6px; }
        .cmp-drop .cmp-num { color:#dc2626; }
        .cmp-x { text-decoration:none; font-size:10.5px; font-weight:800; color:#fff; background:#dc2626; padding:1px 7px; border-radius:999px; margin-left:4px; }
        .cmp-new { color:#166534; }
        .cmp-new .cmp-num { color:#16a34a; }
        .cmp-arrow { text-align:center; color:#94a3b8; font-weight:800; }
        .cmp-note { padding:7px 12px; font-size:12px; color:#475569; background:#fffbeb; border-top:1px solid #fde68a; }

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
