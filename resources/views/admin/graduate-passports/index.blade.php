<x-app-layout>
    @push('styles')
    <style>
        .gp-container { padding: 16px; }
        .gp-summary { margin-bottom: 16px; padding: 12px 20px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
        .gp-summary-num { font-size: 22px; font-weight: 800; color: #1e293b; }
        .gp-summary-label { font-size: 13px; color: #64748b; }
        .gp-summary-filled { color: #16a34a; }
        .gp-summary-unfilled { color: #dc2626; }

        .gp-search-bar { background: linear-gradient(135deg, #f0f4f8, #e8edf5); padding: 12px 20px; border-radius: 10px; margin-bottom: 16px; display: flex; gap: 10px; align-items: center; }
        .gp-search-input { flex: 1; height: 38px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; background: #fff; }
        .gp-search-input:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
        .gp-btn { padding: 8px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; height: 38px; display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; }
        .gp-btn:hover { background: linear-gradient(135deg, #1d4ed8, #2563eb); }

        .gp-faculty { margin-bottom: 20px; }
        .gp-faculty-header { padding: 10px 16px; border-radius: 8px 8px 0 0; color: #fff; font-size: 14px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
        .gp-faculty-body { border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; }
        .gp-group { border-bottom: 1px solid #f1f5f9; }
        .gp-group:last-child { border-bottom: none; }
        .gp-group-header { padding: 8px 16px; background: #f8fafc; font-size: 12px; font-weight: 700; color: #334155; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .gp-group-header:hover { background: #f0f4f8; }
        .gp-group-body { padding: 4px 16px 8px; }
        .gp-student { display: inline-block; padding: 3px 10px; margin: 2px; border-radius: 6px; font-size: 12px; font-weight: 500; }
        .gp-student-filled { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .gp-student-empty { background: #fff; color: #64748b; border: 1px solid #e2e8f0; }
        .gp-counter { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: rgba(255,255,255,0.2); color: #fff; }
        .gp-grp-counter { font-size: 11px; font-weight: 600; }
        .gp-grp-counter-filled { color: #16a34a; }
        .gp-grp-counter-total { color: #64748b; }

        /* Jadval (qidirish natijasi) */
        .gp-table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .gp-table thead th { background: #1e293b; color: #fff; padding: 10px; font-size: 11px; text-transform: uppercase; text-align: left; white-space: nowrap; }
        .gp-table tbody td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
        .gp-table tbody tr:hover { background: #f0f9ff; }
        .gp-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .gp-file-btn { display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; text-decoration: none; border: 1px solid #cbd5e1; color: #334155; background: #fff; }
        .gp-file-btn:hover { background: #f0f9ff; border-color: #2563eb; color: #2563eb; }
        .gp-pagination { display: flex; gap: 4px; justify-content: center; padding: 12px; flex-wrap: wrap; }
        .gp-pg-btn { padding: 5px 10px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .gp-pg-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }
    </style>
    @endpush

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Bitiruvchilar ma'lumotlari</h2>
    </x-slot>

    <div class="gp-container">
        <!-- Umumiy statistika -->
        <div class="gp-summary">
            <div>
                <span class="gp-summary-num">{{ $totalStudents }}</span>
                <span class="gp-summary-label">jami bitiruvchi</span>
            </div>
            <div>
                <span class="gp-summary-num gp-summary-filled">{{ $totalFilled }}</span>
                <span class="gp-summary-label">to'ldirgan</span>
            </div>
            <div>
                <span class="gp-summary-num gp-summary-unfilled">{{ $totalStudents - $totalFilled }}</span>
                <span class="gp-summary-label">to'ldirilmagan</span>
            </div>
        </div>

        <!-- Fakultet → Guruh → Talabalar -->
        @php $colors = ['#1e40af','#047857','#b45309','#7c3aed','#dc2626','#0891b2','#4338ca','#be123c','#15803d','#0369a1']; $ci = 0; @endphp
        @foreach($byFaculty as $facName => $facData)
            <div class="gp-faculty">
                <div class="gp-faculty-header" style="background:{{ $colors[$ci % count($colors)] }};">
                    <span>{{ $facName }}</span>
                    <span class="gp-counter">{{ $facData['filled'] }} / {{ $facData['total'] }}</span>
                </div>
                <div class="gp-faculty-body">
                    @foreach($facData['groups'] as $grpName => $grpData)
                        <div class="gp-group">
                            <div class="gp-group-header" onclick="$(this).next().toggle()">
                                <span>{{ $grpName }}</span>
                                <span class="gp-grp-counter">
                                    <span class="gp-grp-counter-filled">{{ $grpData['filled'] }}</span>
                                    <span class="gp-grp-counter-total">/ {{ $grpData['total'] }}</span>
                                </span>
                            </div>
                            <div class="gp-group-body">
                                @foreach($grpData['students'] as $st)
                                    <span class="gp-student {{ $st->filled ? 'gp-student-filled' : 'gp-student-empty' }}">{{ $st->full_name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @php $ci++; @endphp
        @endforeach

        <!-- Qidirish (to'ldirganlar orasidan) -->
        <div style="margin-top:24px;">
            <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:8px;">To'ldirilgan ma'lumotlarni qidirish</h3>
            <div class="gp-search-bar">
                <input type="text" id="f-search" class="gp-search-input" placeholder="Ism, student ID, passport, JSHSHIR, guruh..." onkeyup="if(event.key==='Enter')loadData(1)">
                <button class="gp-btn" onclick="loadData(1)">Qidirish</button>
            </div>
        </div>

        <div id="search-result" style="display:none;">
            <div id="result-count" style="margin-bottom:8px;font-size:13px;font-weight:700;color:#1e40af;"></div>
            <div style="overflow-x:auto;">
                <table class="gp-table">
                    <thead><tr>
                        <th>#</th><th>FISH</th><th>Student ID</th><th>Ism (UZ)</th><th>Ism (EN)</th>
                        <th>Passport</th><th>JSHSHIR</th><th>Fakultet</th><th>Guruh</th><th>Fayllar</th><th>Sana</th>
                    </tr></thead>
                    <tbody id="table-body"></tbody>
                </table>
            </div>
            <div id="pagination" class="gp-pagination"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var dataUrl = '{{ route("admin.graduate-passports.data") }}';
        var fileUrlBase = '{{ url("/admin/graduate-passports") }}';

        function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function loadData(page) {
            var search = $('#f-search').val();
            if (!search) { $('#search-result').hide(); return; }

            $.ajax({
                url: dataUrl, type: 'GET',
                data: { page: page, per_page: 50, search: search },
                success: function(res) {
                    if (!res.data || res.data.length === 0) {
                        $('#result-count').text('Topilmadi');
                        $('#table-body').html('');
                        $('#pagination').html('');
                        $('#search-result').show();
                        return;
                    }
                    $('#result-count').text('Topildi: ' + res.total + ' ta');
                    var html = '';
                    for (var i = 0; i < res.data.length; i++) {
                        var r = res.data[i];
                        var files = '';
                        if (r.has_front) files += '<a href="' + fileUrlBase + '/' + r.id + '/file/passport_front_path" target="_blank" class="gp-file-btn">Old</a> ';
                        if (r.has_back) files += '<a href="' + fileUrlBase + '/' + r.id + '/file/passport_back_path" target="_blank" class="gp-file-btn">Orqa</a> ';
                        if (r.has_foreign) files += '<a href="' + fileUrlBase + '/' + r.id + '/file/foreign_passport_path" target="_blank" class="gp-file-btn">Xorijiy</a>';
                        var nameUz = [r.last_name, r.first_name, r.father_name].filter(Boolean).join(' ');
                        html += '<tr>';
                        html += '<td style="font-weight:700;color:#2563eb;">' + r.row_num + '</td>';
                        html += '<td style="font-weight:700;">' + esc(r.full_name) + '</td>';
                        html += '<td style="font-size:11px;color:#64748b;">' + esc(r.student_id_number) + '</td>';
                        html += '<td>' + esc(nameUz) + '</td>';
                        html += '<td>' + esc(r.name_en) + '</td>';
                        html += '<td><span class="gp-badge" style="background:#e0e7ff;color:#3730a3;">' + esc(r.passport) + '</span></td>';
                        html += '<td style="font-size:12px;">' + esc(r.jshshir || '-') + '</td>';
                        html += '<td style="font-size:12px;">' + esc(r.department_name) + '</td>';
                        html += '<td><span class="gp-badge" style="background:#f0fdf4;color:#166534;">' + esc(r.group_name) + '</span></td>';
                        html += '<td>' + (files || '-') + '</td>';
                        html += '<td style="font-size:11px;color:#64748b;">' + esc(r.created_at) + '</td>';
                        html += '</tr>';
                    }
                    $('#table-body').html(html);
                    var pgHtml = '';
                    if (res.last_page > 1) {
                        for (var p = 1; p <= res.last_page; p++) {
                            pgHtml += '<button class="gp-pg-btn ' + (p === res.current_page ? 'gp-pg-active' : '') + '" onclick="loadData(' + p + ')">' + p + '</button>';
                        }
                    }
                    $('#pagination').html(pgHtml);
                    $('#search-result').show();
                }
            });
        }
    </script>
</x-app-layout>
