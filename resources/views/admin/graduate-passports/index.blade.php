<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Bitiruvchilar ma'lumotlari</h2>
    </x-slot>

    <div class="py-2">
        <div class="max-w-full mx-auto sm:px-2 lg:px-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                {{-- Filtr --}}
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="flex:1; min-width:300px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet (bakalavr)</label>
                            <select id="faculty-select" class="filter-input" style="height:38px;" onchange="onFacultyChange()">
                                <option value="">— Barcha fakultetlar —</option>
                                @foreach($faculties as $f)
                                    <option value="{{ $f->department_id }}" data-total="{{ $f->total }}" data-filled="{{ $f->filled }}">
                                        {{ $f->department_name }} ({{ $f->filled }}/{{ $f->total }} to'ldirgan)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1; min-width:300px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Guruhni tanlang</label>
                            <select id="group-select" class="filter-input" style="height:38px;" onchange="loadGroup()">
                                <option value="">— Guruh tanlang —</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->group_id }}" data-department-id="{{ $g->department_id }}">
                                        {{ $g->group_name }} ({{ $g->filled }}/{{ $g->total }} to'ldirgan)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Loading --}}
                <div id="loading" style="display:none;text-align:center;padding:40px;">
                    <div class="spinner"></div>
                    <p style="color:#2b5ea7;font-size:14px;margin-top:12px;font-weight:600;">Yuklanmoqda...</p>
                </div>

                {{-- Empty --}}
                <div id="empty" style="text-align:center;padding:50px 20px;">
                    <svg style="width:48px;height:48px;margin:0 auto 10px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/></svg>
                    <p style="color:#64748b;font-size:15px;font-weight:600;">Guruhni tanlang</p>
                </div>

                {{-- Jadval --}}
                <div id="table-area" style="display:none;">
                    <div style="padding:10px 20px;background:#f0f9ff;border-bottom:1px solid #bae6fd;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span id="group-info" style="font-size:14px;font-weight:700;color:#0f172a;"></span>
                        <span id="stat-filled" class="stat-badge" style="background:#16a34a;"></span>
                        <span id="stat-empty" class="stat-badge" style="background:#dc2626;"></span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="journal-table">
                            <thead>
                                <tr>
                                    <th style="width:44px;text-align:center;">#</th>
                                    <th>Talaba FISH</th>
                                    <th>Student ID</th>
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
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var dataUrl = '{{ route("admin.graduate-passports.data") }}';
        var fileUrl = '{{ url("/admin/graduate-passports") }}';

        function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        // Barcha guruh option'larini saqlash (fakultet filtrida qayta tiklash uchun)
        var allGroupOptions = [];
        $(document).ready(function() {
            $('#group-select option').each(function() {
                allGroupOptions.push({
                    value: $(this).val(),
                    text: $(this).text(),
                    departmentId: $(this).data('department-id') || ''
                });
            });
        });

        function onFacultyChange() {
            var facultyId = $('#faculty-select').val();
            var $groupSelect = $('#group-select');

            // Guruh dropdownni fakultetga moslab qayta to'ldirish
            $groupSelect.empty();
            $groupSelect.append('<option value="">— Guruh tanlang —</option>');
            allGroupOptions.forEach(function(opt) {
                if (!opt.value) return; // "Guruh tanlang"
                if (!facultyId || String(opt.departmentId) === String(facultyId)) {
                    $groupSelect.append('<option value="' + opt.value + '" data-department-id="' + opt.departmentId + '">' + esc(opt.text) + '</option>');
                }
            });

            // Fakultet tanlanganda — o'sha fakultet bitiruvchilarini ko'rsatish
            loadGroup();
        }

        function loadGroup() {
            var groupId = $('#group-select').val();
            var facultyId = $('#faculty-select').val();

            $('#empty').hide(); $('#table-area').hide(); $('#loading').show();

            var params = {};
            if (groupId) params.group_id = groupId;
            if (facultyId) params.department_id = facultyId;

            $.get(dataUrl, params, function(res) {
                $('#loading').hide();
                var students = res.students || [];
                if (students.length === 0) {
                    $('#loading').hide();
                    var emptyMsg = "To'ldirgan talaba yo'q";
                    if (groupId) emptyMsg = "Talabalar topilmadi";
                    else if (facultyId) emptyMsg = "Bu fakultetda bitiruvchi topilmadi";
                    $('#empty').show().find('p').text(emptyMsg);
                    return;
                }

                var filled = students.filter(function(s) { return s.filled; }).length;
                var emptyCount = students.length - filled;
                var title;
                if (groupId) {
                    title = $('#group-select option:selected').text().split('(')[0].trim();
                } else if (facultyId) {
                    title = $('#faculty-select option:selected').text().split('(')[0].trim() + ' — barcha bitiruvchilar';
                } else {
                    title = 'Barcha to\'ldirganlar';
                }
                var groupName = title;

                $('#group-info').text(groupName + ' — ' + students.length + ' ta talaba');
                $('#stat-filled').text("To'ldirgan: " + filled);
                $('#stat-empty').text("To'ldirilmagan: " + emptyCount);

                var html = '';
                for (var i = 0; i < students.length; i++) {
                    var s = students[i];
                    var isFilled = s.filled;

                    // Status badge
                    var status = isFilled
                        ? '<span class="badge-filled">To\'ldirgan</span>'
                        : '<span class="badge-empty">To\'ldirilmagan</span>';

                    // Ma'lumotlar — accordion faqat to'ldirganlar uchun
                    var info = '<span style="color:#cbd5e1;">-</span>';
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
                    var files = '<span style="color:#cbd5e1;">-</span>';
                    if (isFilled && s.gp_id) {
                        var btns = '';
                        if (s.has_front) btns += '<a href="' + fileUrl + '/' + s.gp_id + '/file/passport_front_path" target="_blank" class="file-btn">Old</a>';
                        if (s.has_back) btns += '<a href="' + fileUrl + '/' + s.gp_id + '/file/passport_back_path" target="_blank" class="file-btn">Orqa</a>';
                        if (s.has_foreign) btns += '<a href="' + fileUrl + '/' + s.gp_id + '/file/foreign_passport_path" target="_blank" class="file-btn file-btn-purple">Xorijiy</a>';
                        files = btns || '<span style="color:#cbd5e1;">-</span>';
                    }

                    var rowBg = isFilled ? 'background:#f0fdf4;' : '';
                    html += '<tr style="' + rowBg + '">';
                    html += '<td style="text-align:center;font-weight:700;color:#2b5ea7;">' + (i + 1) + '</td>';
                    html += '<td style="font-weight:600;color:#0f172a;font-size:12px;text-transform:uppercase;">' + esc(s.full_name) + '</td>';
                    html += '<td style="font-size:11px;color:#64748b;font-weight:500;">' + esc(s.student_id_number) + '</td>';
                    html += '<td><span style="background:linear-gradient(135deg,#1a3268,#2b5ea7);color:#fff;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;">' + esc(s.group_name) + '</span></td>';
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

        // Sahifa ochilganda barcha to'ldirganlarni yuklash
        $(document).ready(function() { loadGroup(); });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .filter-item { display: flex; flex-direction: column; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .filter-input { height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; }
        .filter-input:hover { border-color: #2b5ea7; }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }

        .spinner { width: 36px; height: 36px; margin: 0 auto; border: 3px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .stat-badge { display: inline-block; padding: 5px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #fff; }

        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even):not([style*="background"]) { background: #f8fafc; }
        .journal-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .journal-table td { padding: 10px 10px; vertical-align: middle; }

        .badge-filled { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #166534; }
        .badge-empty { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #991b1b; }

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
