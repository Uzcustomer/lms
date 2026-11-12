<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost tekshirish
        </h2>
    </x-slot>

    @if(isset($dekanFacultyIds) && count($dekanFacultyIds) === 1)
        <input type="hidden" id="dekan_faculty_id" value="{{ $dekanFacultyIds[0] }}">
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Vedomostlar holati: YN tugagan fanlar — kim topshirgan / tasdiqlagan, PDF -->
                <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;padding:12px 20px;border-bottom:1px solid #e2e8f0;background:#fff;">
                    <span style="font-size:13px;color:#64748b;">YN imtihonlari tugagan fanlar bo'yicha qaydnomani kim topshirgan, kim topshirmagan va dekan tasdiqlaganlari (PDF)</span>
                    <a href="{{ route('admin.yn-qaydnomalar.index') }}"
                       style="display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 18px;border-radius:9px;background:#1e3a8a;color:#fff;font-size:13.5px;font-weight:700;text-decoration:none;box-shadow:0 2px 6px rgba(30,58,138,.25);">
                        <svg style="width:17px;height:17px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Vedomostlar holati
                    </a>
                </div>

                @include('admin.vedomost_tekshirish.partials.filters')

                <!-- RESULTS AREA -->
                <div id="results-area" style="display:none;">
                    <!-- Results header bar -->
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;color:#334155;">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" style="width:15px;height:15px;cursor:pointer;">
                                Barchasi tanlash
                            </label>
                            <span id="selected-count" style="font-size:13px;color:#64748b;"></span>
                        </div>
                        <button id="btn-sync-hemis" class="btn-sync-hemis btn-disabled" disabled onclick="doSyncHemis()">
                            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span id="sync-btn-text">HEMIS yangilash</span>
                        </button>
                        <button id="btn-generate" class="btn-excel btn-disabled" disabled onclick="openWeightModal()">
                            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Generatsiya Excel
                        </button>
                    </div>
                    <!-- Table -->
                    <div style="max-height:calc(100vh - 360px);overflow-y:auto;overflow-x:auto;">
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <th style="width:34px;text-align:center;">☐</th>
                                    <th style="width:36px;">#</th>
                                    <th style="min-width:140px;">Fakultet</th>
                                    <th style="min-width:130px;">Yo'nalish</th>
                                    <th style="width:70px;text-align:center;">Kurs</th>
                                    <th style="width:80px;text-align:center;">Semestr</th>
                                    <th style="min-width:100px;">Guruh</th>
                                    <th style="min-width:260px;">Fan nomi</th>
                                    <th style="width:60px;text-align:center;">Kredit</th>
                                    <th style="width:110px;text-align:center;">Dars boshlanish</th>
                                    <th style="width:110px;text-align:center;">Dars tugash</th>
                                    <th style="width:110px;text-align:center;">OSKI sanasi</th>
                                    <th style="width:110px;text-align:center;">Test sanasi</th>
                                </tr>
                            </thead>
                            <tbody id="results-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- EMPTY STATE -->
                <div id="empty-state" style="padding:60px 20px;text-align:center;">
                    <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Qidirish" tugmasini bosing</p>
                    <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Natijalar shu yerda ko'rsatiladi</p>
                </div>
                <div id="no-results" style="display:none;padding:40px 20px;text-align:center;">
                    <p style="color:#94a3b8;font-size:14px;">Mos ma'lumot topilmadi</p>
                </div>
            </div>
        </div>
    </div>

    <!-- WEIGHT MODAL -->
    <div id="weight-modal" class="modal-overlay" style="display:none;" onclick="closeModalIfOutside(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title">Og'irlik koeffitsientlari</h3>
                <button class="modal-close" onclick="closeWeightModal()">&#x2715;</button>
            </div>
            <p style="font-size:12px;color:#64748b;margin-bottom:16px;">JN + MT + ON + OSKI + Test = 100% bo'lishi shart</p>
            <div class="modal-weights">
                <div class="mw-row">
                    <label class="mw-label">JN (%)</label>
                    <input type="number" id="m_jn"   value="50" min="0" max="100" class="mw-input" oninput="checkModalSum()">
                </div>
                <div class="mw-row">
                    <label class="mw-label">MT (%)</label>
                    <input type="number" id="m_mt"   value="20" min="0" max="100" class="mw-input" oninput="checkModalSum()">
                </div>
                <div class="mw-row">
                    <label class="mw-label">ON (%)</label>
                    <input type="number" id="m_on"   value="0"  min="0" max="100" class="mw-input" oninput="checkModalSum()">
                </div>
                <div class="mw-row">
                    <label class="mw-label">OSKI (%)</label>
                    <input type="number" id="m_oski" value="0"  min="0" max="100" class="mw-input" oninput="checkModalSum()">
                </div>
                <div class="mw-row">
                    <label class="mw-label">Test (%)</label>
                    <input type="number" id="m_test" value="30" min="0" max="100" class="mw-input" oninput="checkModalSum()">
                </div>
            </div>
            <div class="mw-row" style="margin-top:14px;padding-top:12px;border-top:1px dashed #cbd5e1;">
                <label class="mw-label" for="m_pass">O'tish bali</label>
                <input type="number" id="m_pass" value="70" min="1" max="100" class="mw-input" oninput="checkModalSum()">
            </div>
            <p style="font-size:11px;color:#64748b;margin-top:4px;">JN, MT, ON, OSKI yoki Test shu baldan past bo'lsa — o'tmagan (0) hisoblanadi</p>
            <div id="modal-sum-display" style="margin-top:12px;font-size:14px;font-weight:700;text-align:center;padding:8px;border-radius:8px;background:#f1f5f9;">
                Jami: <span id="modal-sum-val" style="color:#16a34a;">100%</span>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
                <button class="modal-cancel-btn" onclick="closeWeightModal()">Bekor qilish</button>
                <button id="modal-download-btn" class="modal-download-btn" onclick="doExport()">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Yuklab olish
                </button>
            </div>
        </div>
    </div>

    <!-- HIDDEN EXPORT FORM -->
    <form id="export-form" method="POST" action="{{ route('admin.vedomost-tekshirish.export') }}" target="_blank" style="display:none;">
        @csrf
        <div id="export-rows-container"></div>
        <input type="hidden" name="weight_jn"   id="ef_jn">
        <input type="hidden" name="weight_mt"   id="ef_mt">
        <input type="hidden" name="weight_on"   id="ef_on">
        <input type="hidden" name="weight_oski" id="ef_oski">
        <input type="hidden" name="weight_test" id="ef_test">
        <input type="hidden" name="pass_score"  id="ef_pass">
    </form>

    @include('admin.vedomost_tekshirish.partials.filters-js')

    <script>
        var searchData = [];

        function doSearch() {
            var params = collectParams();

            $('#empty-state').hide(); $('#no-results').hide(); $('#results-area').hide();
            $('#search-loading').css('display','flex');
            $('#btn-search').prop('disabled',true);

            $.ajax({
                url: '{{ route("admin.vedomost-tekshirish.search") }}',
                type: 'GET', data: params, timeout: 60000,
                success: function(data) {
                    $('#search-loading').hide(); $('#btn-search').prop('disabled',false);
                    searchData = data;
                    if (!data || data.length === 0) {
                        $('#no-results').show();
                    } else {
                        renderTable(data);
                        $('#results-area').show();
                        updateSelectedCount();
                    }
                },
                error: function() {
                    $('#search-loading').hide(); $('#btn-search').prop('disabled',false);
                    $('#empty-state').show().find('p:first').text('Xatolik yuz berdi. Qayta urinib ko\'ring.');
                }
            });
        }

        function esc(s){ return $('<span>').text(s||'').html(); }
        function dateCell(d){ return d ? '<span class="date-badge">'+esc(d)+'</span>' : '<span style="color:#94a3b8;">—</span>'; }

        function renderTable(data) {
            var html='';
            for(var i=0;i<data.length;i++){
                var r=data[i];
                var journalUrl='{{ url("admin/journal/show") }}/'+r.group_pk+'/'+r.subject_id+'/'+r.semester_code;
                html+='<tr data-idx="'+i+'" data-journal="'+journalUrl+'" style="cursor:pointer;" class="journal-row">';
                html+='<td style="text-align:center;" onclick="event.stopPropagation();"><input type="checkbox" class="row-cb" data-idx="'+i+'" onchange="onRowCheck()" style="width:15px;height:15px;cursor:pointer;"></td>';
                html+='<td class="td-num">'+(i+1)+'</td>';
                html+='<td><span class="text-cell" style="color:#0f172a;font-size:12px;">'+esc(r.faculty_name||'—')+'</span></td>';
                html+='<td><span class="text-cell text-cyan">'+esc(r.specialty_name||'—')+'</span></td>';
                html+='<td style="text-align:center;font-weight:600;color:#6d28d9;">'+esc(r.level_name||'—')+'</td>';
                html+='<td style="text-align:center;font-weight:600;color:#0369a1;">'+esc(r.semester_name||'—')+'</td>';
                html+='<td><span class="badge badge-indigo">'+esc(r.group_name)+'</span></td>';
                html+='<td><span class="text-cell text-subject">'+esc(r.subject_name)+'</span></td>';
                html+='<td style="text-align:center;font-weight:700;color:#475569;">'+esc(r.credit||'')+'</td>';
                html+='<td style="text-align:center;">'+dateCell(r.date_start)+'</td>';
                html+='<td style="text-align:center;">'+dateCell(r.date_end)+'</td>';
                html+='<td style="text-align:center;">'+dateCell(r.oski_date)+'</td>';
                html+='<td style="text-align:center;">'+dateCell(r.test_date)+'</td>';
                html+='</tr>';
            }
            $('#results-body').html(html);
            $('#select-all').prop('checked',false).prop('indeterminate',false);

            // Qatorga bosganda jurnal sahifasiga o'tish
            $('#results-body').off('click', '.journal-row').on('click', '.journal-row', function(){
                window.open($(this).data('journal'), '_blank');
            });
        }

        function onRowCheck() {
            updateSelectAllState();
            updateSelectedCount();
            updateGenBtn();
        }

        function toggleSelectAll(cb) {
            $('.row-cb').prop('checked', cb.checked);
            updateSelectedCount();
            updateGenBtn();
        }

        function updateSelectAllState() {
            var all=$('.row-cb').length, checked=$('.row-cb:checked').length;
            var sa=$('#select-all');
            sa.prop('checked', checked===all && all>0);
            sa.prop('indeterminate', checked>0 && checked<all);
        }

        function updateSelectedCount() {
            var c=$('.row-cb:checked').length;
            $('#selected-count').text(c>0 ? c+' ta tanlangan' : '');
        }

        function updateGenBtn() {
            var c=$('.row-cb:checked').length;
            var btn=$('#btn-generate');
            var syncBtn=$('#btn-sync-hemis');
            if(c>0){
                btn.prop('disabled',false).removeClass('btn-disabled').addClass('btn-active');
                syncBtn.prop('disabled',false).removeClass('btn-disabled').addClass('btn-sync-active');
            } else {
                btn.prop('disabled',true).addClass('btn-disabled').removeClass('btn-active');
                syncBtn.prop('disabled',true).addClass('btn-disabled').removeClass('btn-sync-active');
            }
        }

        // Weight modal
        function openWeightModal() {
            if($('.row-cb:checked').length===0) return;

            // Tanlangan qatorlarda oski_date, test_date va sinov fani borligini aniqlash
            var hasOski = false, hasTest = false, hasSinov = false;
            $('.row-cb:checked').each(function(){
                var idx = parseInt($(this).data('idx'));
                var r = searchData[idx];
                if(r && r.oski_date) hasOski = true;
                if(r && r.test_date) hasTest = true;
                if(r && r.closing_form === 'sinov') hasSinov = true;
            });

            // Default vaznlarni belgilash
            if(hasSinov){
                // Sinov (test) fani: JN=50, MT=20, OSKI=0, Test=30
                $('#m_jn').val(50); $('#m_mt').val(20); $('#m_on').val(0);
                $('#m_oski').val(0); $('#m_test').val(30);
            } else if(hasOski && hasTest){
                // Ikkalasi ham bor: JN=50, MT=20, OSKI=15, Test=15
                $('#m_jn').val(50); $('#m_mt').val(20); $('#m_on').val(0);
                $('#m_oski').val(15); $('#m_test').val(15);
            } else if(hasOski || hasTest){
                // Faqat bittasi bor: JN=50, MT=20, OSKI yoki Test=30
                $('#m_jn').val(50); $('#m_mt').val(20); $('#m_on').val(0);
                $('#m_oski').val(hasOski ? 30 : 0);
                $('#m_test').val(hasTest ? 30 : 0);
            } else {
                // Ikkalasi ham yo'q: JN=80, MT=20
                $('#m_jn').val(80); $('#m_mt').val(20); $('#m_on').val(0);
                $('#m_oski').val(0); $('#m_test').val(0);
            }

            checkModalSum();
            document.getElementById('weight-modal').style.display='flex';
        }
        function closeWeightModal() {
            document.getElementById('weight-modal').style.display='none';
        }
        function closeModalIfOutside(e) {
            if(e.target===document.getElementById('weight-modal')) closeWeightModal();
        }
        function checkModalSum() {
            var sum=[parseInt($('#m_jn').val()||0),parseInt($('#m_mt').val()||0),
                     parseInt($('#m_on').val()||0),parseInt($('#m_oski').val()||0),
                     parseInt($('#m_test').val()||0)].reduce(function(a,b){return a+b;},0);
            var el=$('#modal-sum-val');
            el.text(sum+'%');
            el.css('color', sum===100?'#16a34a':'#dc2626');
            var pass=parseInt($('#m_pass').val()||0);
            var passOk = pass>=1 && pass<=100;
            $('#m_pass').css('border-color', passOk?'':'#dc2626');
            $('#modal-download-btn').prop('disabled', sum!==100 || !passOk);
            return sum===100 && passOk;
        }

        function getSelectedRows() {
            var selected=[];
            $('.row-cb:checked').each(function(){
                var idx=$(this).data('idx');
                var r=searchData[idx];
                selected.push({group_id:r.group_pk, subject_id:r.subject_id, semester_code:r.semester_code});
            });
            return selected;
        }

        function doSyncHemis() {
            var selected=getSelectedRows();
            if(selected.length===0){ alert('Qator tanlanmagan'); return; }

            var btn=$('#btn-sync-hemis');
            var txtEl=$('#sync-btn-text');
            btn.prop('disabled',true);
            txtEl.text('Yangilanmoqda...');

            $.ajax({
                url: '{{ route("admin.vedomost-tekshirish.sync-hemis") }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', rows: selected },
                timeout: 60000
            }).done(function(res){
                txtEl.text('Yangilandi (' + (res.synced||0) + ')');
                setTimeout(function(){ txtEl.text('HEMIS yangilash'); btn.prop('disabled',false); }, 3000);
            }).fail(function(){
                txtEl.text('Xatolik!');
                setTimeout(function(){ txtEl.text('HEMIS yangilash'); btn.prop('disabled',false); }, 3000);
            });
        }

        function doExport() {
            if(!checkModalSum()) return;

            var selected=getSelectedRows();
            if(selected.length===0) return;

            // Build form
            var container=$('#export-rows-container');
            container.empty();
            for(var i=0;i<selected.length;i++){
                container.append('<input type="hidden" name="rows['+i+'][group_id]" value="'+selected[i].group_id+'">');
                container.append('<input type="hidden" name="rows['+i+'][subject_id]" value="'+selected[i].subject_id+'">');
                container.append('<input type="hidden" name="rows['+i+'][semester_code]" value="'+selected[i].semester_code+'">');
            }
            $('#ef_jn').val($('#m_jn').val());
            $('#ef_mt').val($('#m_mt').val());
            $('#ef_on').val($('#m_on').val());
            $('#ef_oski').val($('#m_oski').val());
            $('#ef_test').val($('#m_test').val());
            $('#ef_pass').val($('#m_pass').val());

            closeWeightModal();
            $('#export-form').submit();
        }
    </script>

    @include('admin.vedomost_tekshirish.partials.styles')
</x-app-layout>
