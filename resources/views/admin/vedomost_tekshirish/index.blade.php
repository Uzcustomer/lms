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

                <!-- FILTERS -->
                <div class="filter-container">
                    <!-- Row 1 -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width:155px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width:100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}">{{ $type->education_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1;min-width:200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width:100%;">
                                @if(!isset($dekanFacultyIds) || empty($dekanFacultyIds))
                                    <option value="">Barchasi</option>
                                @endif
                                @foreach($faculties as $fac)
                                    <option value="{{ $fac->id }}">{{ $fac->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1.5;min-width:240px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty" class="select2" style="width:100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width:145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> YN sanasi (dan)</label>
                            <input type="text" id="yn_date_from" class="date-input" placeholder="kk.oo.yyyy" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width:145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> YN sanasi (gacha)</label>
                            <input type="text" id="yn_date_to" class="date-input" placeholder="kk.oo.yyyy" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width:150px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                    </div>
                    <!-- Row 2 -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width:130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width:100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width:140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width:100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="flex:1;min-width:190px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#1a3268;"></span> Guruh
                                <span id="groups-count" style="font-weight:400;font-size:10px;margin-left:3px;color:#64748b;"></span>
                            </label>
                            <select id="groups" class="select2-multi" multiple style="width:100%;"></select>
                        </div>
                        <div class="filter-item" style="flex:1;min-width:210px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Kafedra</label>
                            <select id="department" class="select2" style="width:100%;">
                                <option value="">Barchasi</option>
                                @foreach($kafedras as $kaf)
                                    <option value="{{ $kaf->department_id }}">{{ $kaf->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1.5;min-width:240px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#0f172a;"></span> Fan
                                <span id="subjects-count" style="font-weight:400;font-size:10px;margin-left:3px;color:#64748b;"></span>
                            </label>
                            <select id="subjects" class="select2-multi" multiple style="width:100%;"></select>
                        </div>
                        <div style="display:flex;align-items:flex-end;padding-bottom:2px;gap:8px;">
                            <div id="search-loading" style="display:none;align-items:center;color:#2b5ea7;">
                                <svg class="animate-spin" style="height:16px;width:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity:.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path style="opacity:.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <button type="button" id="btn-search" class="btn-calc" onclick="doSearch()">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Qidirish
                            </button>
                        </div>
                    </div>
                </div>

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
                                    <th style="min-width:110px;">Guruh</th>
                                    <th style="min-width:130px;">Yo'nalish</th>
                                    <th style="min-width:280px;">Fan nomi</th>
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
    </form>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <script>
        var searchData = [];

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g,'').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }
        function toggleSemester() {
            document.getElementById('current-semester-toggle').classList.toggle('active');
        }

        $(document).ready(function () {
            // Single selects
            $('.select2').each(function () {
                $(this).select2({ theme:'classic', width:'100%', allowClear:true, placeholder:$(this).find('option:first').text(), matcher:fuzzyMatcher })
                .on('select2:open', function () { setTimeout(function(){ var s=document.querySelector('.select2-container--open .select2-search__field'); if(s)s.focus(); },10); });
            });

            // Multi selects
            $('.select2-multi').each(function () {
                $(this).select2({ theme:'classic', width:'100%', placeholder:'Tanlang...', closeOnSelect:false, matcher:fuzzyMatcher })
                .on('select2:open', function () { setTimeout(function(){ var s=document.querySelector('.select2-container--open .select2-search__field'); if(s)s.focus(); },10); });
            });

            $('#groups').on('change', function () {
                var c=$(this).val()?$(this).val().length:0;
                $('#groups-count').text(c>0?'('+c+' ta)':'');
            });
            $('#subjects').on('change', function () {
                var c=$(this).val()?$(this).val().length:0;
                $('#subjects-count').text(c>0?'('+c+' ta)':'');
            });

            // Calendar
            new ScrollCalendar('yn_date_from');
            new ScrollCalendar('yn_date_to');

            // Cascading filters
            function fp() {
                var df=document.getElementById('dekan_faculty_id');
                return {
                    education_type: $('#education_type').val()||'',
                    faculty_id: df ? df.value : ($('#faculty').val()||''),
                    specialty_id: $('#specialty').val()||'',
                    department_id: $('#department').val()||'',
                    level_code: $('#level_code').val()||'',
                    semester_code: $('#semester_code').val()||'',
                    current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1':'0',
                };
            }
            function rd(el){ $(el).empty().append('<option value="">Barchasi</option>'); }
            function pd(url,p,el,cb){ $.get(url,p,function(d){ $.each(d,function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb)cb(); }); }
            function pdu(url,p,el,cb){ $.get(url,p,function(d){ var u={}; $.each(d,function(k,v){ if(!u[v])u[v]=k; }); $.each(u,function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb)cb(); }); }
            function pdm(url,p,el,ph){ var prev=$(el).val()||[]; $(el).empty(); $.get(url,p,function(d){ var seen={}; $.each(d,function(k,v){ if(!seen[v]){seen[v]=true;$(el).append('<option value="'+k+'">'+v+'</option>');} }); var vals=$(el).find('option').map(function(){return this.value;}).get(); var keep=prev.filter(function(v){return vals.indexOf(v)>=0;}); if(keep.length)$(el).val(keep); $(el).trigger('change'); }); }

            function rSpec(){ rd('#specialty'); pdu('{{ route("admin.journal.get-specialties") }}',fp(),'#specialty'); }
            function rGrp(){ pdm('{{ route("admin.journal.get-groups") }}',fp(),'#groups'); }
            function rSubj(){ pdm('{{ route("admin.journal.get-subjects") }}',fp(),'#subjects'); }

            $('#education_type').change(function(){ rSpec(); rSubj(); rGrp(); });
            $('#faculty').change(function(){ rSpec(); rSubj(); rGrp(); });
            $('#specialty').change(function(){ rGrp(); rSubj(); });
            $('#department').change(function(){ rSubj(); rGrp(); });
            $('#level_code').change(function(){
                var lc=$(this).val(); rd('#semester_code');
                if(lc) pd('{{ route("admin.journal.get-semesters") }}',{level_code:lc},'#semester_code');
                rSubj(); rGrp();
            });
            $('#semester_code').change(function(){ rSubj(); rGrp(); });

            // Initial
            pdu('{{ route("admin.journal.get-specialties") }}',fp(),'#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}',{},'#level_code');
            pd('{{ route("admin.journal.get-semesters") }}',{},'#semester_code');
            pdm('{{ route("admin.journal.get-subjects") }}',fp(),'#subjects');
            pdm('{{ route("admin.journal.get-groups") }}',fp(),'#groups');
        });

        function doSearch() {
            var df=document.getElementById('dekan_faculty_id');
            var params = {
                education_type:  $('#education_type').val()||'',
                faculty_id:      df ? df.value : ($('#faculty').val()||''),
                specialty_id:    $('#specialty').val()||'',
                department_id:   $('#department').val()||'',
                level_code:      $('#level_code').val()||'',
                semester_code:   $('#semester_code').val()||'',
                yn_date_from:    $('#yn_date_from').val()||'',
                yn_date_to:      $('#yn_date_to').val()||'',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active')?'1':'0',
            };
            var gv=$('#groups').val(); if(gv&&gv.length) params['group_ids[]']=gv;
            var sv=$('#subjects').val(); if(sv&&sv.length) params['subject_ids[]']=sv;

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
                var rowKey=encodeURIComponent(JSON.stringify({group_id:r.group_pk,subject_id:r.subject_id,semester_code:r.semester_code}));
                html+='<tr data-idx="'+i+'">';
                html+='<td style="text-align:center;"><input type="checkbox" class="row-cb" data-idx="'+i+'" onchange="onRowCheck()" style="width:15px;height:15px;cursor:pointer;"></td>';
                html+='<td class="td-num">'+(i+1)+'</td>';
                html+='<td><span class="badge badge-indigo">'+esc(r.group_name)+'</span></td>';
                html+='<td><span class="text-cell text-cyan">'+esc(r.specialty_name)+'</span></td>';
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
            if(c>0){
                btn.prop('disabled',false).removeClass('btn-disabled').addClass('btn-active');
            } else {
                btn.prop('disabled',true).addClass('btn-disabled').removeClass('btn-active');
            }
        }

        // Weight modal
        function openWeightModal() {
            if($('.row-cb:checked').length===0) return;
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
            $('#modal-download-btn').prop('disabled', sum!==100);
            return sum===100;
        }

        function doExport() {
            if(!checkModalSum()) return;

            var selected=[];
            $('.row-cb:checked').each(function(){
                var idx=$(this).data('idx');
                var r=searchData[idx];
                selected.push({group_id:r.group_pk, subject_id:r.subject_id, semester_code:r.semester_code});
            });
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

            closeWeightModal();
            $('#export-form').submit();
        }
    </script>

    <style>
        .filter-container { padding:16px 20px 12px; background:linear-gradient(135deg,#f0f4f8,#e8edf5); border-bottom:2px solid #dbe4ef; }
        .filter-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; align-items:flex-end; }
        .filter-row:last-child { margin-bottom:0; }
        .filter-label { display:flex; align-items:center; gap:5px; margin-bottom:4px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#475569; }
        .fl-dot { width:7px; height:7px; border-radius:50%; display:inline-block; flex-shrink:0; }

        /* Select2 single */
        .select2-container--classic .select2-selection--single { height:36px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .select2-container--classic .select2-selection--single:hover { border-color:#2b5ea7; }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height:34px; padding-left:10px; padding-right:52px; color:#1e293b; font-size:.8rem; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height:34px; width:22px; background:transparent; border-left:none; right:0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position:absolute; right:22px; top:50%; transform:translateY(-50%); font-size:16px; font-weight:bold; color:#94a3b8; cursor:pointer; padding:2px 6px; z-index:2; background:#fff; border-radius:50%; line-height:1; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color:#fff; background:#ef4444; }

        /* Select2 multiple */
        .select2-container--classic .select2-selection--multiple { border:1px solid #cbd5e1; border-radius:8px; background:#fff; min-height:36px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .select2-container--classic .select2-selection--multiple:hover { border-color:#2b5ea7; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; border-radius:5px; padding:1px 6px; font-size:11px; font-weight:600; margin:3px 3px 0; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice__remove { color:#93c5fd; margin-right:4px; font-weight:700; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice__remove:hover { color:#dc2626; }
        .select2-dropdown { font-size:.8rem; border-radius:8px; border:1px solid #cbd5e1; box-shadow:0 8px 24px rgba(0,0,0,.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color:#2b5ea7; }
        .select2-container--classic .select2-results__option[aria-selected=true] { background:#eff6ff; color:#1e40af; }

        /* Date input */
        .date-input { height:36px; border:1px solid #cbd5e1; border-radius:8px; padding:0 30px 0 10px; font-size:.8rem; font-weight:500; color:#1e293b; background:#fff; width:100%; box-shadow:0 1px 2px rgba(0,0,0,.04); outline:none; }
        .date-input:focus { border-color:#2b5ea7; box-shadow:0 0 0 3px rgba(43,94,167,.15); }
        .date-input::placeholder { color:#94a3b8; font-weight:400; }

        /* Buttons */
        .btn-calc { display:inline-flex; align-items:center; gap:8px; padding:8px 20px; background:linear-gradient(135deg,#2b5ea7,#3b7ddb); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(43,94,167,.3); height:36px; }
        .btn-calc:hover:not(:disabled) { background:linear-gradient(135deg,#1e4b8a,#2b5ea7); }
        .btn-excel { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; height:36px; transition:all .2s; white-space:nowrap; }
        .btn-disabled { background:#94a3b8; cursor:not-allowed; }
        .btn-active { background:linear-gradient(135deg,#16a34a,#22c55e); cursor:pointer; box-shadow:0 2px 8px rgba(22,163,74,.3); }
        .btn-active:hover { background:linear-gradient(135deg,#15803d,#16a34a); transform:translateY(-1px); }

        /* Toggle */
        .toggle-switch { display:inline-flex; align-items:center; gap:10px; cursor:pointer; padding:6px 0; height:36px; user-select:none; }
        .toggle-track { width:40px; height:22px; background:#cbd5e1; border-radius:11px; position:relative; transition:background .25s; flex-shrink:0; }
        .toggle-switch.active .toggle-track { background:linear-gradient(135deg,#2b5ea7,#3b7ddb); }
        .toggle-thumb { width:18px; height:18px; background:#fff; border-radius:50%; position:absolute; top:2px; left:2px; transition:transform .25s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
        .toggle-switch.active .toggle-thumb { transform:translateX(18px); }
        .toggle-label { font-size:12px; font-weight:600; color:#64748b; white-space:nowrap; }
        .toggle-switch.active .toggle-label { color:#1e3a5f; }

        /* Result table */
        .result-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .result-table thead { position:sticky; top:0; z-index:10; }
        .result-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef); }
        .result-table th { padding:12px 10px; text-align:left; font-weight:600; font-size:11.5px; color:#334155; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; border-bottom:2px solid #cbd5e1; }
        .result-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
        .result-table tbody tr:nth-child(even) { background:#f8fafc; }
        .result-table tbody tr:nth-child(odd)  { background:#fff; }
        .result-table tbody tr:hover { background:#eff6ff !important; }
        .result-table tbody tr.selected { background:#dbeafe !important; }
        .result-table td { padding:9px 10px; vertical-align:middle; }
        .td-num { font-weight:700; color:#2b5ea7; font-size:13px; padding-left:12px !important; }

        .badge-indigo { display:inline-block; padding:3px 9px; background:linear-gradient(135deg,#1a3268,#2b5ea7); color:#fff; border-radius:6px; font-size:11.5px; font-weight:600; white-space:nowrap; }
        .text-cell { font-size:12.5px; font-weight:500; line-height:1.35; display:block; }
        .text-cyan { color:#0e7490; max-width:200px; white-space:normal; word-break:break-word; }
        .text-subject { color:#0f172a; font-weight:700; font-size:12.5px; max-width:340px; white-space:normal; word-break:break-word; }
        .date-badge { display:inline-block; padding:3px 8px; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; border-radius:5px; font-size:12px; font-weight:600; white-space:nowrap; }

        /* Modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; display:flex; align-items:center; justify-content:center; }
        .modal-box { background:#fff; border-radius:16px; padding:28px 32px; width:360px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,.25); }
        .modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
        .modal-title { font-size:18px; font-weight:700; color:#0f172a; }
        .modal-close { background:none; border:none; font-size:20px; color:#94a3b8; cursor:pointer; padding:2px 6px; border-radius:6px; }
        .modal-close:hover { color:#ef4444; background:#fef2f2; }
        .modal-weights { display:flex; flex-direction:column; gap:10px; }
        .mw-row { display:flex; align-items:center; gap:12px; }
        .mw-label { min-width:70px; font-size:13px; font-weight:700; color:#334155; }
        .mw-input { flex:1; height:38px; border:1px solid #cbd5e1; border-radius:8px; text-align:center; font-size:14px; font-weight:700; padding:0 8px; outline:none; }
        .mw-input:focus { border-color:#2b5ea7; box-shadow:0 0 0 3px rgba(43,94,167,.15); }
        .modal-cancel-btn { padding:8px 20px; border:1px solid #cbd5e1; background:#fff; border-radius:8px; font-size:13px; font-weight:600; color:#334155; cursor:pointer; }
        .modal-cancel-btn:hover { background:#f1f5f9; }
        .modal-download-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 22px; background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 8px rgba(22,163,74,.3); }
        .modal-download-btn:hover:not(:disabled) { background:linear-gradient(135deg,#15803d,#16a34a); }
        .modal-download-btn:disabled { opacity:.5; cursor:not-allowed; }
    </style>
</x-app-layout>
