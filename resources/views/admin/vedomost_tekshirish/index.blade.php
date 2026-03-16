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

                <form id="export-form" method="POST" action="{{ route('admin.vedomost-tekshirish.export') }}" target="_blank">
                    @csrf
                    <input type="hidden" name="semester_code" id="hidden_semester_code">

                    <div class="filter-container">
                        <!-- Row 1: Ta'lim turi, Fakultet, Yo'nalish -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                                <select id="education_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}">{{ $type->education_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                                <select id="faculty" class="select2" style="width: 100%;">
                                    @if(!isset($dekanFacultyIds) || empty($dekanFacultyIds))
                                        <option value="">Barchasi</option>
                                    @endif
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1.5; min-width: 240px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                                <select id="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2: Kurs, Semestr, Guruh(multi), Kafedra, Fan(multi), Og'irlik, Excel -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select id="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                                <select id="semester_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 200px; flex: 1;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#1a3268;"></span> Guruh
                                    <span id="groups-count" style="font-weight:400;font-size:10px;margin-left:3px;color:#64748b;"></span>
                                </label>
                                <select id="groups" name="group_ids[]" class="select2-multi" multiple style="width: 100%;">
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Kafedra</label>
                                <select id="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($kafedras as $kafedra)
                                        <option value="{{ $kafedra->department_id }}">{{ $kafedra->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1.5; min-width: 240px;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#0f172a;"></span> Fan
                                    <span id="subjects-count" style="font-weight:400;font-size:10px;margin-left:3px;color:#64748b;"></span>
                                </label>
                                <select id="subjects" name="subject_ids[]" class="select2-multi" multiple style="width: 100%;">
                                </select>
                            </div>

                            <div class="filter-item">
                                <label class="filter-label" style="white-space:nowrap;">Og'irlik (JN+MT+ON+OSKI+Test)</label>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                                    <div class="w-item"><span class="w-label">JN</span><input type="number" name="weight_jn"   id="w_jn"   value="50" min="0" max="100" class="w-input"><span class="w-pct">%</span></div>
                                    <div class="w-item"><span class="w-label">MT</span><input type="number" name="weight_mt"   id="w_mt"   value="20" min="0" max="100" class="w-input"><span class="w-pct">%</span></div>
                                    <div class="w-item"><span class="w-label">ON</span><input type="number" name="weight_on"   id="w_on"   value="0"  min="0" max="100" class="w-input"><span class="w-pct">%</span></div>
                                    <div class="w-item"><span class="w-label">OSKI</span><input type="number" name="weight_oski" id="w_oski" value="0"  min="0" max="100" class="w-input"><span class="w-pct">%</span></div>
                                    <div class="w-item"><span class="w-label">Test</span><input type="number" name="weight_test" id="w_test" value="30" min="0" max="100" class="w-input"><span class="w-pct">%</span></div>
                                    <div id="w-sum" class="w-sum">100%</div>
                                </div>
                            </div>

                            <div style="display:flex;align-items:flex-end;padding-bottom:2px;gap:6px;">
                                <div id="export-loading" style="display:none;align-items:center;color:#2b5ea7;">
                                    <svg class="animate-spin" style="height:16px;width:16px;margin-right:4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity:.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity:.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <button type="submit" id="export-btn" class="btn-excel" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Empty state -->
                <div style="padding:60px 20px;text-align:center;">
                    <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Excel" tugmasini bosing</p>
                    <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Fan va guruhlarni belgilang (bir nechta tanlash mumkin)</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        $(document).ready(function () {
            // Single selects
            $('.select2').each(function () {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function () { setTimeout(function () { var s = document.querySelector('.select2-container--open .select2-search__field'); if (s) s.focus(); }, 10); });
            });

            // Multi selects
            $('.select2-multi').each(function () {
                $(this).select2({ theme: 'classic', width: '100%', placeholder: 'Tanlang...', closeOnSelect: false, matcher: fuzzyMatcher })
                .on('select2:open', function () { setTimeout(function () { var s = document.querySelector('.select2-container--open .select2-search__field'); if (s) s.focus(); }, 10); });
            });

            // Count badges
            $('#groups').on('change', function () {
                var c = $(this).val() ? $(this).val().length : 0;
                $('#groups-count').text(c > 0 ? '(' + c + ' ta)' : '');
                updateBtn();
            });
            $('#subjects').on('change', function () {
                var c = $(this).val() ? $(this).val().length : 0;
                $('#subjects-count').text(c > 0 ? '(' + c + ' ta)' : '');
                updateBtn();
            });

            function fp() {
                var df = document.getElementById('dekan_faculty_id');
                return {
                    education_type: $('#education_type').val() || '',
                    faculty_id: df ? df.value : ($('#faculty').val() || ''),
                    specialty_id: $('#specialty').val() || '',
                    department_id: $('#department').val() || '',
                    level_code: $('#level_code').val() || '',
                    semester_code: $('#semester_code').val() || '',
                };
            }

            function rd(el, ph) { $(el).empty().append('<option value="">' + (ph || 'Barchasi') + '</option>'); }
            function pd(url, p, el, cb) {
                $.get(url, p, function (d) {
                    $.each(d, function (k, v) { $(el).append('<option value="' + k + '">' + v + '</option>'); });
                    if (cb) cb();
                });
            }
            function pdu(url, p, el, cb) {
                $.get(url, p, function (d) {
                    var u = {};
                    $.each(d, function (k, v) { if (!u[v]) u[v] = k; });
                    $.each(u, function (n, k) { $(el).append('<option value="' + k + '">' + n + '</option>'); });
                    if (cb) cb();
                });
            }
            function pdMulti(url, p, el, cb) {
                var prev = $(el).val() || [];
                $(el).empty();
                $.get(url, p, function (d) {
                    var seen = {};
                    $.each(d, function (k, v) {
                        if (!seen[v]) {
                            seen[v] = true;
                            $(el).append('<option value="' + k + '">' + v + '</option>');
                        }
                    });
                    // Restore previous selection if still available
                    if (prev.length) {
                        var vals = $(el).find('option').map(function () { return this.value; }).get();
                        var keep = prev.filter(function (v) { return vals.indexOf(v) >= 0; });
                        if (keep.length) $(el).val(keep);
                    }
                    $(el).trigger('change');
                    if (cb) cb();
                });
            }

            function rSpec() { rd('#specialty'); pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty'); }
            function rGrp()  { pdMulti('{{ route("admin.journal.get-groups") }}', fp(), '#groups'); }
            function rSubj() { pdMulti('{{ route("admin.journal.get-subjects") }}', fp(), '#subjects'); }

            $('#education_type').change(function () { rSpec(); rSubj(); rGrp(); });
            $('#faculty').change(function () { rSpec(); rSubj(); rGrp(); });
            $('#specialty').change(function () { rGrp(); rSubj(); });
            $('#department').change(function () { rSubj(); rGrp(); });
            $('#level_code').change(function () {
                var lc = $(this).val();
                rd('#semester_code');
                if (lc) pd('{{ route("admin.journal.get-semesters") }}', { level_code: lc }, '#semester_code');
                rSubj(); rGrp();
            });
            $('#semester_code').change(function () {
                $('#hidden_semester_code').val($(this).val());
                rSubj(); rGrp();
                updateBtn();
            });

            // Weight
            $('.w-input').on('input', function () { checkWeightSum(); updateBtn(); });

            function checkWeightSum() {
                var sum = [parseInt($('#w_jn').val() || 0), parseInt($('#w_mt').val() || 0),
                           parseInt($('#w_on').val() || 0), parseInt($('#w_oski').val() || 0),
                           parseInt($('#w_test').val() || 0)].reduce(function (a, b) { return a + b; }, 0);
                $('#w-sum').text(sum + '%').css('color', sum === 100 ? '#16a34a' : '#dc2626');
                return sum === 100;
            }

            function updateBtn() {
                var hasSemester = !!$('#semester_code').val();
                var hasSubjects = $('#subjects').val() && $('#subjects').val().length > 0;
                var hasGroups   = $('#groups').val()   && $('#groups').val().length   > 0;
                var weightOk    = checkWeightSum();
                var ok = hasSemester && hasSubjects && hasGroups && weightOk;
                $('#export-btn').prop('disabled', !ok).toggleClass('btn-excel-active', ok);
            }

            // Submit
            $('#export-form').on('submit', function (e) {
                if ($('#export-btn').prop('disabled')) { e.preventDefault(); return; }
                $('#export-loading').css('display', 'flex');
                $('#export-btn').prop('disabled', true);
                setTimeout(function () {
                    $('#export-loading').hide();
                    updateBtn();
                }, 6000);
            });

            // Initial load
            pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty');
            pd('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code');
            pd('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code');
            pdMulti('{{ route("admin.journal.get-subjects") }}', fp(), '#subjects');
            pdMulti('{{ route("admin.journal.get-groups") }}', fp(), '#groups');
            checkWeightSum();
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        /* Single select2 */
        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }

        /* Multi select2 */
        .select2-container--classic .select2-selection--multiple { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; min-height: 36px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .select2-container--classic .select2-selection--multiple:hover { border-color: #2b5ea7; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; border-radius: 5px; padding: 1px 6px; font-size: 11px; font-weight: 600; margin: 3px 3px 0; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice__remove { color: #93c5fd; margin-right: 4px; font-weight: 700; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice__remove:hover { color: #dc2626; }

        /* Dropdown */
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }
        .select2-container--classic .select2-results__option[aria-selected=true] { background: #eff6ff; color: #1e40af; }

        /* Weight inputs */
        .w-item { display: flex; align-items: center; gap: 2px; }
        .w-label { font-size: 10px; font-weight: 700; color: #475569; min-width: 28px; }
        .w-input { width: 40px; height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; font-size: 12px; font-weight: 600; padding: 0 3px; outline: none; }
        .w-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,.12); }
        .w-pct { font-size: 11px; color: #64748b; }
        .w-sum { font-size: 13px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: #f1f5f9; margin-left: 4px; white-space: nowrap; }

        /* Excel button */
        .btn-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background: #94a3b8; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: not-allowed; box-shadow: 0 2px 8px rgba(22,163,74,.15); height: 36px; white-space: nowrap; transition: all .2s; }
        .btn-excel.btn-excel-active { background: linear-gradient(135deg, #16a34a, #22c55e); cursor: pointer; box-shadow: 0 2px 8px rgba(22,163,74,.3); }
        .btn-excel.btn-excel-active:hover { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22,163,74,.4); }
    </style>
</x-app-layout>
