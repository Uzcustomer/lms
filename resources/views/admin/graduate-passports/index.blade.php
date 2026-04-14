<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Bitiruvchilar ma'lumotlari
            </h2>
            <div class="text-sm text-gray-600">
                Jami bitiruvchilar: <span class="font-bold text-indigo-700">{{ $stats->total ?? 0 }}</span>
                <span class="text-gray-400 mx-1">·</span>
                <span class="text-blue-700 font-semibold">{{ $stats->male ?? 0 }}</span> erkak,
                <span class="text-pink-700 font-semibold">{{ $stats->female ?? 0 }}</span> ayol
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">

                {{-- Filters --}}
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="flex:1; min-width:400px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Guruhni tanlang (bakalavr bitiruvchilari)</label>
                            <select id="group-select" class="filter-input" style="height:38px;" onchange="loadGroup()">
                                <option value="">— Guruh tanlang —</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->group_id }}">{{ $g->group_name }} ({{ $g->filled }}/{{ $g->total }} to'ldirgan)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width:150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Holat</label>
                            <select id="status" class="filter-input">
                                <option value="">Barchasi</option>
                                <option value="filled">To'ldirgan</option>
                                <option value="empty">To'ldirilmagan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1; min-width:200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Qidiruv (FIO / Student ID)</label>
                            <input type="text" id="search" class="filter-input" placeholder="FIO yoki talaba ID">
                        </div>
                        <div class="filter-item" style="min-width:130px;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" class="btn-apply" onclick="applyFilter()">
                                <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Qidirish
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Summary cards --}}
                <div id="summary-row" style="display:none;padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <div class="stat-card" style="background:#eff6ff;border-color:#bfdbfe;">
                            <div class="stat-label">Jami</div>
                            <div class="stat-value" style="color:#1d4ed8;" id="stat-total">0</div>
                        </div>
                        <div class="stat-card" style="background:#eff6ff;border-color:#bfdbfe;">
                            <div class="stat-label">Erkak</div>
                            <div class="stat-value" style="color:#2563eb;" id="stat-male">0</div>
                        </div>
                        <div class="stat-card" style="background:#fdf2f8;border-color:#fbcfe8;">
                            <div class="stat-label">Ayol</div>
                            <div class="stat-value" style="color:#be185d;" id="stat-female">0</div>
                        </div>
                        <div class="stat-card" style="background:#f0fdf4;border-color:#bbf7d0;">
                            <div class="stat-label">To'ldirgan</div>
                            <div class="stat-value" style="color:#15803d;" id="stat-filled">0</div>
                        </div>
                        <div class="stat-card" style="background:#fef2f2;border-color:#fecaca;">
                            <div class="stat-label">To'ldirilmagan</div>
                            <div class="stat-value" style="color:#b91c1c;" id="stat-empty">0</div>
                        </div>
                    </div>
                </div>

                {{-- Loading --}}
                <div id="loading" style="display:none;text-align:center;padding:40px;">
                    <div class="spinner"></div>
                    <p style="color:#2b5ea7;font-size:14px;margin-top:12px;font-weight:600;">Yuklanmoqda...</p>
                </div>

                {{-- Empty --}}
                <div id="empty" style="display:none;text-align:center;padding:50px 20px;">
                    <svg style="width:48px;height:48px;margin:0 auto 10px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/></svg>
                    <p style="color:#64748b;font-size:15px;font-weight:600;">Talabalar topilmadi</p>
                </div>

                {{-- Table --}}
                <div id="table-area" style="display:none;overflow-x:auto;">
                    <table class="gp-table">
                        <thead>
                            <tr>
                                <th style="width:44px;text-align:center;">#</th>
                                <th>Talaba FISH</th>
                                <th>Student ID</th>
                                <th style="text-align:center;">Jinsi</th>
                                <th>Fakultet / Kafedra</th>
                                <th>Guruh</th>
                                <th style="text-align:center;">Holat</th>
                                <th>Ma'lumotlar</th>
                                <th style="text-align:center;">Fayllar</th>
                            </tr>
                        </thead>
                        <tbody id="table-body"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var dataUrl = '{{ route("admin.graduate-passports.data") }}';
        var fileUrl = '{{ url("/admin/graduate-passports") }}';

        function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function loadGroup() {
            var groupId = $('#group-select').val();

        // Qidiruv input — Enter
        $('#search').on('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilter(); }
        });

        function applyFilter() {
            $('#empty').hide(); $('#table-area').hide(); $('#loading').show();

            var params = groupId ? { group_id: groupId } : {};

            $.get(dataUrl, params, function(res) {
                $('#loading').hide();
                var students = res.students || [];
                var stats = res.stats || {};

                $('#stat-total').text(stats.total || 0);
                $('#stat-male').text(stats.male || 0);
                $('#stat-female').text(stats.female || 0);
                $('#stat-filled').text(stats.filled || 0);
                $('#stat-empty').text(stats.empty || 0);
                $('#summary-row').show();

                if (students.length === 0) {
                    $('#loading').hide();
                    $('#empty').show().find('p').text(groupId ? "Talabalar topilmadi" : "To'ldirgan talaba yo'q");
                    return;
                }

                var filled = students.filter(function(s) { return s.filled; }).length;
                var emptyCount = students.length - filled;
                var groupName = groupId
                    ? $('#group-select option:selected').text().split('(')[0].trim()
                    : 'Barcha to\'ldirganlar';

                $('#group-info').text(groupName + ' — ' + students.length + ' ta talaba');
                $('#stat-filled').text("To'ldirgan: " + filled);
                $('#stat-empty').text("To'ldirilmagan: " + emptyCount);

                var html = '';
                for (var i = 0; i < students.length; i++) {
                    var s = students[i];
                    var isFilled = s.filled;

                    // Gender badge
                    var genderBadge = '';
                    if (s.gender_code === '11') {
                        genderBadge = '<span class="gender-badge gender-male">♂ Erkak</span>';
                    } else if (s.gender_code === '12') {
                        genderBadge = '<span class="gender-badge gender-female">♀ Ayol</span>';
                    } else {
                        genderBadge = '<span style="color:#94a3b8;">—</span>';
                    }

                    // Status badge
                    var status = isFilled
                        ? '<span class="badge-filled">To\'ldirgan</span>'
                        : '<span class="badge-empty">To\'ldirilmagan</span>';

                    // Faculty / Department
                    var facDept = '';
                    if (s.faculty_name) {
                        facDept += '<div style="font-size:12px;font-weight:600;color:#0f172a;">' + esc(s.faculty_name) + '</div>';
                    }
                    if (s.kafedra_name) {
                        facDept += '<div style="font-size:11px;color:#64748b;margin-top:1px;">' + esc(s.kafedra_name) + '</div>';
                    }
                    if (!facDept) facDept = '<span style="color:#cbd5e1;">—</span>';

                    // Ma'lumotlar
                    var info = '<span style="color:#cbd5e1;">—</span>';
                    if (isFilled) {
                        var accId = 'acc-' + i;
                        info = '<div class="acc-toggle" onclick="toggleAcc(\'' + accId + '\',this)">';
                        info += '<svg class="acc-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>';
                        info += '<span style="font-weight:600;color:#1e293b;">' + esc(s.name_uz || s.full_name) + '</span>';
                        info += '</div>';
                        info += '<div class="acc-body" id="' + accId + '">';
                        if (s.name_en) info += '<div class="acc-row"><span class="acc-label">English:</span> ' + esc(s.name_en) + '</div>';
                        if (s.passport) info += '<div class="acc-row"><span class="acc-label">Passport:</span> <strong>' + esc(s.passport) + '</strong></div>';
                        if (s.jshshir) info += '<div class="acc-row"><span class="acc-label">JSHSHIR:</span> ' + esc(s.jshshir) + '</div>';
                        if (s.created_at) info += '<div class="acc-row"><span class="acc-label">Sana:</span> <span style="color:#94a3b8;">' + esc(s.created_at) + '</span></div>';
                        info += '</div>';
                    }

                    // Fayllar
                    var files = '<span style="color:#cbd5e1;">—</span>';
                    if (isFilled && s.gp_id) {
                        var btns = '';
                        if (s.has_front) btns += '<a href="' + fileUrl + '/' + s.gp_id + '/file/passport_front_path" target="_blank" class="file-btn">Old</a>';
                        if (s.has_back) btns += '<a href="' + fileUrl + '/' + s.gp_id + '/file/passport_back_path" target="_blank" class="file-btn">Orqa</a>';
                        if (s.has_foreign) btns += '<a href="' + fileUrl + '/' + s.gp_id + '/file/foreign_passport_path" target="_blank" class="file-btn file-btn-purple">Xorijiy</a>';
                        files = btns || '<span style="color:#cbd5e1;">—</span>';
                    }

                    var rowBg = isFilled ? 'background:#f0fdf4;' : '';
                    html += '<tr style="' + rowBg + '">';
                    html += '<td style="text-align:center;font-weight:700;color:#2b5ea7;">' + (i + 1) + '</td>';
                    html += '<td style="font-weight:600;color:#0f172a;font-size:12px;text-transform:uppercase;">' + esc(s.full_name) + '</td>';
                    html += '<td style="font-size:11px;color:#64748b;font-weight:500;">' + esc(s.student_id_number) + '</td>';
                    html += '<td style="text-align:center;">' + genderBadge + '</td>';
                    html += '<td>' + facDept + '</td>';
                    html += '<td><span class="group-pill">' + esc(s.group_name) + '</span></td>';
                    html += '<td style="text-align:center;">' + status + '</td>';
                    html += '<td>' + info + '</td>';
                    html += '<td style="text-align:center;">' + files + '</td>';
                    html += '</tr>';
                }
                $('#table-body').html(html);
                $('#table-area').show();
            });
        }

        function toggleAcc(id, el) {
            var body = document.getElementById(id);
            var arrow = el.querySelector('.acc-arrow');
            if (body.style.display === 'block') {
                body.style.display = 'none';
                arrow.style.transform = 'rotate(0deg)';
            } else {
                body.style.display = 'block';
                arrow.style.transform = 'rotate(90deg)';
            }
        }

        // Sahifa ochilganda barcha bitiruvchilarni yuklash
        $(document).ready(function() { applyFilter(); });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .filter-item { display: flex; flex-direction: column; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .filter-input { height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; }
        .filter-input:hover { border-color: #2b5ea7; }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }

        .btn-apply { height: 36px; padding: 0 16px; background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; font-size: 12px; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(43,94,167,0.25); transition: all 0.15s; }
        .btn-apply:hover { background: linear-gradient(135deg, #152850, #1e4686); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(43,94,167,0.3); }

        .spinner { width: 36px; height: 36px; margin: 0 auto; border: 3px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .stat-card { padding: 8px 14px; border-radius: 10px; border: 1px solid; min-width: 110px; }
        .stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
        .stat-value { font-size: 22px; font-weight: 800; line-height: 1.1; margin-top: 2px; }

        .gp-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .gp-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .gp-table th { padding: 12px 10px; text-align: left; font-weight: 700; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .gp-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .gp-table tbody tr:nth-child(even):not([style*="background"]) { background: #f8fafc; }
        .gp-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .gp-table td { padding: 10px 10px; vertical-align: middle; }

        .badge-filled { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #166534; }
        .badge-empty { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #991b1b; }

        .gender-badge { display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .gender-male { background: #dbeafe; color: #1e40af; }
        .gender-female { background: #fce7f3; color: #9d174d; }

        .group-pill { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; white-space: nowrap; display: inline-block; }

        .acc-toggle { display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 0; user-select: none; }
        .acc-toggle:hover { color: #2563eb; }
        .acc-arrow { transition: transform 0.2s; color: #94a3b8; flex-shrink: 0; }
        .acc-body { display: none; margin-top: 6px; padding: 8px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .acc-row { font-size: 12px; color: #475569; padding: 2px 0; }
        .acc-label { font-size: 11px; color: #94a3b8; font-weight: 600; }

        .file-btn { display: inline-flex; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-decoration: none; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; transition: all 0.15s; margin: 1px; }
        .file-btn:hover { background: #dbeafe; border-color: #3b82f6; }
        .file-btn-purple { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
        .file-btn-purple:hover { background: #ede9fe; border-color: #8b5cf6; }
    </style>
</x-app-layout>
