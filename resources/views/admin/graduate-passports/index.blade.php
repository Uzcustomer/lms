<x-app-layout>
    @push('styles')
    <style>
        .gp-container { padding: 16px; max-width: 1200px; margin: 0 auto; }
        .gp-select-wrap { margin-bottom: 20px; }
        .gp-select-label { font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; }
        .gp-select { width: 100%; max-width: 400px; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; font-weight: 500; outline: none; background: #fff; cursor: pointer; }
        .gp-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        .gp-loading { text-align: center; padding: 30px; color: #2563eb; display: none; }
        .gp-empty { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; display: none; }

        .gp-table-wrap { overflow-x: auto; }
        .gp-table { width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .gp-table thead th { background: #1e293b; color: #fff; padding: 10px 8px; font-size: 10px; text-transform: uppercase; text-align: left; white-space: nowrap; }
        .gp-table tbody td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .gp-table tbody tr:hover { background: #f0f9ff; }
        .gp-table tbody tr.gp-row-filled { background: #f0fdf4; }
        .gp-table tbody tr.gp-row-empty { }

        .gp-badge-filled { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; background: #dcfce7; color: #166534; }
        .gp-badge-empty { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; background: #fee2e2; color: #991b1b; }

        .gp-file-btn { display: inline-flex; padding: 2px 5px; border-radius: 3px; font-size: 10px; font-weight: 600; text-decoration: none; border: 1px solid #cbd5e1; color: #334155; background: #fff; }
        .gp-file-btn:hover { background: #f0f9ff; border-color: #2563eb; color: #2563eb; }

        .gp-info-cell { font-size: 11px; color: #475569; line-height: 1.4; }
        .gp-info-cell strong { color: #0f172a; }
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>
    @endpush

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Bitiruvchilar ma'lumotlari</h2>
    </x-slot>

    <div class="gp-container">
        <div class="gp-select-wrap">
            <div class="gp-select-label">Guruhni tanlang (bakalavr bitiruvchilari)</div>
            <select id="group-select" class="gp-select" onchange="loadGroup()">
                <option value="">— Guruh tanlang —</option>
                @foreach($groups as $g)
                    <option value="{{ $g->group_id }}">{{ $g->group_name }} ({{ $g->filled }}/{{ $g->total }} to'ldirgan)</option>
                @endforeach
            </select>
        </div>

        <div id="loading" class="gp-loading">
            <div style="width:30px;height:30px;margin:0 auto 8px;border:3px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:spin 0.7s linear infinite;"></div>
            Yuklanmoqda...
        </div>

        <div id="empty" class="gp-empty">Guruhni tanlang</div>

        <div id="table-area" style="display:none;">
            <div id="group-info" style="margin-bottom:10px;font-size:14px;font-weight:700;color:#1e293b;"></div>
            <div class="gp-table-wrap">
                <table class="gp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Talaba FISH</th>
                            <th>Student ID</th>
                            <th>Holat</th>
                            <th>Ma'lumotlar</th>
                            <th>Fayllar</th>
                        </tr>
                    </thead>
                    <tbody id="table-body"></tbody>
                </table>
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
            if (!groupId) { $('#table-area').hide(); $('#empty').show().text('Guruhni tanlang'); return; }

            $('#empty').hide(); $('#table-area').hide(); $('#loading').show();

            $.get(dataUrl, { group_id: groupId }, function(res) {
                $('#loading').hide();
                var students = res.students || [];
                if (students.length === 0) { $('#empty').show().text("Talabalar topilmadi"); return; }

                var filled = students.filter(function(s) { return s.filled; }).length;
                $('#group-info').html($('#group-select option:selected').text().split('(')[0].trim() +
                    ' — <span style="color:#16a34a;">' + filled + '</span> / ' + students.length + ' to\'ldirgan');

                var html = '';
                for (var i = 0; i < students.length; i++) {
                    var s = students[i];
                    var rowClass = s.filled ? 'gp-row-filled' : 'gp-row-empty';
                    var status = s.filled
                        ? '<span class="gp-badge-filled">To\'ldirilgan</span>'
                        : '<span class="gp-badge-empty">To\'ldirilmagan</span>';

                    var info = '-';
                    if (s.filled) {
                        info = '<div class="gp-info-cell">';
                        if (s.name_uz) info += '<strong>' + esc(s.name_uz) + '</strong><br>';
                        if (s.name_en) info += '<span style="color:#64748b;">' + esc(s.name_en) + '</span><br>';
                        if (s.passport) info += 'Passport: <strong>' + esc(s.passport) + '</strong><br>';
                        if (s.jshshir) info += 'JSHSHIR: ' + esc(s.jshshir) + '<br>';
                        if (s.created_at) info += '<span style="font-size:10px;color:#94a3b8;">' + esc(s.created_at) + '</span>';
                        info += '</div>';
                    }

                    var files = '-';
                    if (s.filled && s.gp_id) {
                        files = '';
                        if (s.has_front) files += '<a href="' + fileUrl + '/' + s.gp_id + '/file/passport_front_path" target="_blank" class="gp-file-btn">Old</a> ';
                        if (s.has_back) files += '<a href="' + fileUrl + '/' + s.gp_id + '/file/passport_back_path" target="_blank" class="gp-file-btn">Orqa</a> ';
                        if (s.has_foreign) files += '<a href="' + fileUrl + '/' + s.gp_id + '/file/foreign_passport_path" target="_blank" class="gp-file-btn">Xorijiy</a>';
                        if (!files) files = '-';
                    }

                    html += '<tr class="' + rowClass + '">';
                    html += '<td style="font-weight:700;color:#2563eb;">' + (i + 1) + '</td>';
                    html += '<td style="font-weight:600;">' + esc(s.full_name) + '</td>';
                    html += '<td style="font-size:11px;color:#64748b;">' + esc(s.student_id_number) + '</td>';
                    html += '<td>' + status + '</td>';
                    html += '<td>' + info + '</td>';
                    html += '<td>' + files + '</td>';
                    html += '</tr>';
                }
                $('#table-body').html(html);
                $('#table-area').show();
            });
        }
    </script>
</x-app-layout>
