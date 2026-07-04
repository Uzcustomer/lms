<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Test bahosi apelyatsiyasi
        </h2>
    </x-slot>

    <style>
        .qa-wrap { padding: 16px; display:flex; flex-direction:column; gap:16px; }
        .qa-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden; }
        .qa-note { margin:14px 16px; padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; font-size:12.5px; color:#1e3a8a; line-height:1.6; }

        .qa-search-bar { display:flex; gap:8px; padding:14px 16px; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#fff7ed,#ffedd5); flex-wrap:wrap; align-items:center; }
        .qa-search-input { flex:1; min-width:240px; height:38px; padding:0 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; }
        .qa-search-input:focus { outline:none; border-color:#d97706; box-shadow:0 0 0 3px rgba(217,119,6,0.15); }
        .qa-search-btn { height:38px; padding:0 18px; background:linear-gradient(135deg,#d97706,#f59e0b); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
        .qa-search-btn:disabled { opacity:.6; cursor:not-allowed; }
        .qa-search-hint { font-size:11.5px; color:#9a3412; width:100%; }

        .qa-table-wrap { overflow-x:auto; }
        table.qa-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .qa-table thead th { padding:10px 12px; text-align:left; background:linear-gradient(135deg,#e0e7ff,#c7d2fe); font-weight:700; font-size:11px; color:#334155; text-transform:uppercase; letter-spacing:.04em; border-bottom:2px solid #a5b4fc; white-space:nowrap; }
        .qa-table tbody tr { border-bottom:1px solid #f1f5f9; }
        .qa-table tbody tr:hover { background:#eff6ff; }
        .qa-table td { padding:9px 12px; vertical-align:middle; }
        .qa-badge { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; }
        .qa-badge-replace { background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; }
        .qa-badge-delete { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .qa-old { color:#991b1b; text-decoration:line-through; }
        .qa-new { color:#166534; font-weight:700; }
        .qa-doc { color:#1d4ed8; text-decoration:none; font-weight:600; }
        .qa-doc:hover { text-decoration:underline; }
        .qa-empty { padding:40px 20px; text-align:center; color:#64748b; }
        .qa-spinner { display:inline-block; width:18px; height:18px; border:3px solid #e2e8f0; border-top-color:#d97706; border-radius:50%; animation:qa-spin .8s linear infinite; vertical-align:middle; margin-right:8px; }
        @keyframes qa-spin { to { transform:rotate(360deg); } }

        .appeal-btn { padding:4px 10px; font-size:11px; font-weight:700; color:#fff; background:linear-gradient(135deg,#d97706,#f59e0b); border:none; border-radius:6px; cursor:pointer; white-space:nowrap; }
        .appeal-btn:hover { background:linear-gradient(135deg,#b45309,#d97706); }
        .appeal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:center; justify-content:center; padding:16px; }
        .appeal-modal { background:#fff; border-radius:14px; max-width:520px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.35); overflow:hidden; }
        .appeal-modal-head { padding:14px 18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; background:linear-gradient(135deg,#fff7ed,#ffedd5); }
        .appeal-modal-title { font-size:16px; font-weight:800; color:#9a3412; }
        .appeal-close { background:transparent; border:none; font-size:24px; line-height:1; cursor:pointer; color:#64748b; }
        .appeal-close:hover { color:#dc2626; }
        .appeal-modal-body { padding:16px 18px; }
        .appeal-info { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:13px; color:#334155; margin-bottom:14px; line-height:1.7; }
        .appeal-label { display:block; font-size:12px; font-weight:700; color:#475569; margin:10px 0 4px; }
        .appeal-input { width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; box-sizing:border-box; }
        .appeal-input:focus { outline:none; border-color:#d97706; box-shadow:0 0 0 3px rgba(217,119,6,0.15); }
        .appeal-error { color:#b91c1c; font-size:12px; font-weight:600; margin-top:8px; min-height:16px; }
        .appeal-btn-cancel { padding:8px 16px; border:1px solid #d1d5db; border-radius:8px; background:#fff; color:#374151; font-size:13px; font-weight:600; cursor:pointer; }
        .appeal-btn-submit { padding:8px 16px; border:none; border-radius:8px; background:linear-gradient(135deg,#d97706,#f59e0b); color:#fff; font-size:13px; font-weight:700; cursor:pointer; }
        .appeal-btn-submit:disabled { opacity:.6; cursor:not-allowed; }
    </style>

    <div class="qa-wrap">
        <!-- Qidiruv + amal -->
        <div class="qa-card">
            <div class="qa-note">
                Sistemaga adashib yuklangan test bahosini tuzatish uchun avval talabani <strong>F.I.Sh, HEMIS ID yoki Talaba ID</strong>
                bo'yicha qidiring, so'ng kerakli qatordagi <strong>Apelyatsiya</strong> tugmasini bosing.
                Bahoni almashtirish yoki o'chirish uchun asoslovchi hujjat (PDF/JPG/PNG) yuklash majburiy.
            </div>

            <div class="qa-search-bar">
                <input type="text" id="qaSearchInput" class="qa-search-input"
                       placeholder="Masalan: Turdiyeva yoki 368231100181 yoki 6081"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();qaSearch();}">
                <button type="button" class="qa-search-btn" id="qaSearchBtn" onclick="qaSearch()">Qidirish</button>
                <div class="qa-search-hint">Kamida 2 ta belgi kiriting.</div>
            </div>

            <div class="qa-table-wrap">
                <table class="qa-table">
                    <thead>
                        <tr>
                            <th>F.I.Sh.</th>
                            <th>Fakultet</th>
                            <th>Yo'nalish</th>
                            <th>Guruh</th>
                            <th>Kurs</th>
                            <th>Semestr</th>
                            <th>Fan</th>
                            <th>Turi</th>
                            <th>Quiz turi</th>
                            <th>Shakl</th>
                            <th>Sana</th>
                            <th>Baho</th>
                            <th style="text-align:center;">Amallar</th>
                        </tr>
                    </thead>
                    <tbody id="qaResultsBody">
                        <tr><td colspan="13" class="qa-empty">Talabani qidiring.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tarix -->
        <div class="qa-card">
            <div style="padding:12px 16px;border-bottom:1px solid #e2e8f0;font-weight:800;color:#334155;font-size:13px;">
                So'nggi apelyatsiyalar
            </div>
            <div class="qa-table-wrap">
                <table class="qa-table">
                    <thead>
                        <tr>
                            <th>Sana</th>
                            <th>Talaba</th>
                            <th>Fan</th>
                            <th>Amal</th>
                            <th>Eski &rarr; Yangi</th>
                            <th>Sabab</th>
                            <th>Hujjat</th>
                            <th>Kim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($appeals ?? []) as $a)
                            <tr>
                                <td style="white-space:nowrap;">{{ $a->created_at?->format('d.m.Y H:i') }}</td>
                                <td>
                                    <div style="font-weight:700;color:#0f172a;">{{ $a->student_name ?: '-' }}</div>
                                    <div style="font-size:11px;color:#64748b;">{{ $a->student_hemis_id }}</div>
                                </td>
                                <td>{{ $a->subject_name ?: ('#' . $a->subject_id) }}</td>
                                <td>
                                    @if($a->action === 'delete')
                                        <span class="qa-badge qa-badge-delete">O'chirildi</span>
                                    @else
                                        <span class="qa-badge qa-badge-replace">Almashtirildi</span>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">
                                    <span class="qa-old">{{ $a->old_grade !== null ? rtrim(rtrim(number_format($a->old_grade,2,'.',''),'0'),'.') : '-' }}</span>
                                    @if($a->action !== 'delete')
                                        &rarr; <span class="qa-new">{{ $a->new_grade !== null ? rtrim(rtrim(number_format($a->new_grade,2,'.',''),'0'),'.') : '-' }}</span>
                                    @endif
                                </td>
                                <td style="max-width:280px;">{{ $a->reason }}</td>
                                <td>
                                    @if($a->document_path)
                                        <a class="qa-doc" target="_blank"
                                           href="{{ route($routePrefix . '.quiz-grade-appeals.download', $a->id) }}">
                                            📎 {{ \Illuminate\Support\Str::limit($a->document_original_name ?: 'hujjat', 24) }}
                                        </a>
                                    @else
                                        <span style="color:#94a3b8;">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $a->performed_by_name ?: '-' }}</div>
                                    <div style="font-size:11px;color:#64748b;">{{ $a->performed_by_role }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="qa-empty">Hozircha apelyatsiya yo'q.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($appeals && $appeals->hasPages())
                <div style="padding:12px 16px;">{{ $appeals->links() }}</div>
            @endif
        </div>
    </div>

    <!-- Apelyatsiya modal -->
    <div id="appealOverlay" class="appeal-overlay">
        <div class="appeal-modal">
            <div class="appeal-modal-head">
                <div class="appeal-modal-title">Test bahosi apelyatsiyasi</div>
                <button type="button" class="appeal-close" onclick="closeAppeal()">&times;</button>
            </div>
            <div class="appeal-modal-body">
                <div class="appeal-info" id="appealInfo"></div>
                <form id="appealForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source" id="ap_source" value="grade">
                    <input type="hidden" name="student_grade_id" id="ap_grade_id">
                    <input type="hidden" name="retake_application_id" id="ap_retake_id">
                    <input type="hidden" name="component" id="ap_component">
                    <label class="appeal-label">Amal</label>
                    <div style="display:flex;gap:16px;margin-bottom:10px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="radio" name="action" value="replace" checked onchange="apToggleAction()"> Bahoni almashtirish
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="radio" name="action" value="delete" onchange="apToggleAction()"> Bahoni o'chirish
                        </label>
                    </div>
                    <div id="ap_grade_wrap">
                        <label class="appeal-label">Yangi baho (0–100)</label>
                        <input type="number" name="new_grade" id="ap_new_grade" class="appeal-input" min="0" max="100" step="0.01" placeholder="Masalan: 85">
                    </div>
                    <div id="ap_delete_hint" style="display:none;font-size:11.5px;color:#9a3412;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 10px;margin-top:4px;"></div>
                    <label class="appeal-label">Sabab / asoslash</label>
                    <textarea name="reason" id="ap_reason" class="appeal-input" rows="3" placeholder="Nima uchun tuzatilyapti..."></textarea>
                    <label class="appeal-label">Asoslovchi hujjat (PDF/JPG/PNG, ≤5MB) — majburiy</label>
                    <input type="file" name="document" id="ap_document" class="appeal-input" accept=".pdf,.jpg,.jpeg,.png">
                    <div id="ap_error" class="appeal-error"></div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                        <button type="button" class="appeal-btn-cancel" onclick="closeAppeal()">Bekor qilish</button>
                        <button type="submit" class="appeal-btn-submit" id="ap_submit">Qo'llash</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var searchUrl = '{{ route($routePrefix . ".quiz-grade-appeals.search") }}';
        var appealStoreUrl = '{{ route($routePrefix . ".quiz-grade-appeals.store") }}';
        var appealCsrf = '{{ csrf_token() }}';
        var qaLastRows = [];

        function esc(s) { return $('<span>').text(s === null || s === undefined ? '-' : s).html(); }

        // ========== QIDIRUV ==========
        function qaSearch() {
            var q = ($('#qaSearchInput').val() || '').trim();
            if (q.length < 2) {
                $('#qaResultsBody').html('<tr><td colspan="13" class="qa-empty">Kamida 2 ta belgi kiriting.</td></tr>');
                return;
            }
            $('#qaSearchBtn').prop('disabled', true);
            $('#qaResultsBody').html('<tr><td colspan="13" class="qa-empty"><span class="qa-spinner"></span>Qidirilmoqda...</td></tr>');

            $.ajax({
                url: searchUrl, type: 'GET', data: { q: q }, timeout: 60000,
                success: function(res) {
                    if (!res.success) {
                        $('#qaResultsBody').html('<tr><td colspan="13" class="qa-empty" style="color:#b91c1c;">Xatolik yuz berdi.</td></tr>');
                        return;
                    }
                    qaLastRows = res.rows || [];
                    qaRenderResults(qaLastRows);
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Xatolik yuz berdi.';
                    $('#qaResultsBody').html('<tr><td colspan="13" class="qa-empty" style="color:#b91c1c;">' + esc(msg) + '</td></tr>');
                },
                complete: function() { $('#qaSearchBtn').prop('disabled', false); }
            });
        }

        function qaRenderResults(rows) {
            if (!rows.length) {
                $('#qaResultsBody').html('<tr><td colspan="13" class="qa-empty">Natija topilmadi.</td></tr>');
                return;
            }
            var html = '';
            rows.forEach(function(r) {
                html += '<tr>';
                html += '<td style="font-weight:700;color:#0f172a;">' + esc(r.student_name) + '<div style="font-size:11px;color:#64748b;font-weight:400;">' + esc(r.student_hemis_id) + '</div></td>';
                html += '<td>' + esc(r.faculty) + '</td>';
                html += '<td>' + esc(r.direction) + '</td>';
                html += '<td>' + esc(r.group) + '</td>';
                html += '<td style="white-space:nowrap;">' + esc(r.kurs) + '</td>';
                html += '<td style="white-space:nowrap;">' + esc(r.semester) + '</td>';
                html += '<td>' + esc(r.fan_name) + '</td>';
                var kindColor = r.kind === 'mavzu' ? 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;'
                    : (r.kind === 'retake' ? 'background:#dcfce7;color:#166534;border:1px solid #86efac;'
                    : 'background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;');
                html += '<td><span class="qa-badge" style="' + kindColor + '">' + esc(r.kind_label) + '</span></td>';
                html += '<td>' + esc(r.quiz_type) + '</td>';
                html += '<td>' + esc(r.shakl) + '</td>';
                html += '<td style="white-space:nowrap;">' + esc(r.date) + '</td>';
                html += '<td style="text-align:center;font-weight:700;">' + esc(r.grade) + '</td>';
                html += '<td style="text-align:center;white-space:nowrap;">'
                    + '<button type="button" class="appeal-btn" '
                    + 'data-id="' + esc(r.id) + '" '
                    + 'data-source="' + esc(r.source || 'grade') + '" '
                    + 'data-retake-id="' + esc(r.retake_application_id || '') + '" '
                    + 'data-component="' + esc(r.component || '') + '" '
                    + 'data-student="' + esc(r.student_name) + '" '
                    + 'data-fan="' + esc(r.fan_name) + '" '
                    + 'data-semester="' + esc(r.semester) + '" '
                    + 'data-kind="' + esc(r.kind) + '" '
                    + 'data-kindlabel="' + esc(r.kind_label) + '" '
                    + 'data-grade="' + esc(r.grade) + '">Apelyatsiya</button>'
                    + '</td>';
                html += '</tr>';
            });
            $('#qaResultsBody').html(html);
        }

        // ========== APELYATSIYA ==========
        var apCurrentKind = 'quiz';

        function apToggleAction() {
            var act = $('input[name="action"]:checked').val();
            $('#ap_grade_wrap').toggle(act === 'replace');
            var $hint = $('#ap_delete_hint');
            if (act === 'delete' && apCurrentKind === 'mavzu') {
                $hint.text("Diqqat: bu \"N-mavzu\" qayta topshirish natijasi. O'chirish faqat Moodle orqali qo'yilgan qayta topshirish bahosini bekor qiladi — darsdagi asl baho (agar bo'lsa) o'zgarmaydi.").show();
            } else if (act === 'delete' && apCurrentKind === 'retake') {
                $hint.text("Bu qayta o'qish (retake) natijasi. O'chirilsa tegishli OSKE/Test qiymati bo'shatiladi va yakuniy baho qayta hisoblanadi.").show();
            } else if (act === 'delete') {
                $hint.text("Bu test natijasi yozuvining o'zi butunlay o'chiriladi.").show();
            } else {
                $hint.hide();
            }
        }
        function openAppeal(btn) {
            var $b = $(btn);
            $('#appealForm')[0].reset();
            var source = $b.data('source') || 'grade';
            var kind = $b.data('kind');
            apCurrentKind = kind === 'mavzu' ? 'mavzu' : (kind === 'retake' ? 'retake' : 'quiz');

            $('#ap_source').val(source);
            if (source === 'retake') {
                $('#ap_grade_id').val('');
                $('#ap_retake_id').val($b.data('retake-id'));
                $('#ap_component').val($b.data('component'));
            } else {
                $('#ap_grade_id').val($b.data('id'));
                $('#ap_retake_id').val('');
                $('#ap_component').val('');
            }

            var kindText = $b.data('kindlabel')
                || (apCurrentKind === 'mavzu' ? "Qayta topshirish (mavzu)" : 'OSKI/Test');
            $('#appealInfo').html(
                '<div><strong>Talaba:</strong> ' + esc($b.data('student')) + '</div>' +
                '<div><strong>Fan:</strong> ' + esc($b.data('fan')) + '</div>' +
                '<div><strong>Semestr:</strong> ' + esc($b.data('semester')) + '</div>' +
                '<div><strong>Turi:</strong> ' + esc(kindText) + '</div>' +
                '<div><strong>Joriy baho:</strong> ' + esc($b.data('grade')) + '</div>'
            );
            $('#ap_error').text('');
            apToggleAction();
            $('#appealOverlay').css('display', 'flex');
        }
        function closeAppeal() { $('#appealOverlay').hide(); }

        $(document).on('click', '.appeal-btn', function() { openAppeal(this); });

        $(document).on('submit', '#appealForm', function(e) {
            e.preventDefault();
            var act = $('input[name="action"]:checked').val();
            if (act === 'replace' && $('#ap_new_grade').val() === '') {
                $('#ap_error').text('Yangi bahoni kiriting.'); return;
            }
            if (($('#ap_reason').val() || '').trim().length < 5) {
                $('#ap_error').text('Sabab kamida 5 belgi bo\'lsin.'); return;
            }
            if (!$('#ap_document')[0].files.length) {
                $('#ap_error').text('Asoslovchi hujjatni yuklang.'); return;
            }
            var fd = new FormData(document.getElementById('appealForm'));
            var $btn = $('#ap_submit').prop('disabled', true).text('Yuborilmoqda...');
            fetch(appealStoreUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': appealCsrf, 'Accept': 'application/json' },
                body: fd
            }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
              .then(function(res) {
                if (res.ok && res.j.success) {
                    closeAppeal();
                    alert(res.j.message || 'Bajarildi.');
                    qaSearch(); // natijalarni yangilash
                } else {
                    var msg = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : 'Xatolik yuz berdi.');
                    $('#ap_error').text(msg);
                }
              }).catch(function(err) { $('#ap_error').text('Xato: ' + err.message); })
              .finally(function() { $btn.prop('disabled', false).text('Qo\'llash'); });
        });
    </script>
</x-app-layout>
