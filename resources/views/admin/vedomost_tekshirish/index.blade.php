<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost tekshirish
        </h2>
    </x-slot>

    @if(session('error'))
        <div class="relative px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
            <strong class="font-bold">Xato!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="overflow: visible;">

                <form id="export-form" method="POST" action="{{ route('admin.vedomost-tekshirish.export') }}">
                    @csrf
                    <input type="hidden" name="semester_code" id="hidden_semester_code">
                    <input type="hidden" name="subject_id" id="hidden_subject_id">

                    <div class="filter-container">

                        <!-- Row 1 -->
                        <div class="filter-row">
                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label fl-emerald">
                                    <span class="fl-dot" style="background:#10b981;"></span> Fakultet
                                </label>
                                <select id="faculty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 240px;">
                                <label class="filter-label fl-cyan">
                                    <span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish
                                </label>
                                <select id="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label fl-violet">
                                    <span class="fl-dot" style="background:#8b5cf6;"></span> Kurs
                                </label>
                                <select id="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 150px;">
                                <label class="filter-label fl-teal">
                                    <span class="fl-dot" style="background:#14b8a6;"></span> Semestr
                                </label>
                                <select id="semester_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1.5; min-width: 280px;">
                                <label class="filter-label fl-subject">
                                    <span class="fl-dot" style="background:#0f172a;"></span> Fan
                                </label>
                                <select id="subject" class="select2" style="width: 100%;">
                                    <option value="">Tanlang</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div class="filter-row">
                            <div class="filter-item" style="flex: 2; min-width: 320px;">
                                <label class="filter-label fl-indigo">
                                    <span class="fl-dot" style="background:#1a3268;"></span> Guruhlar
                                    <span id="groups-count" style="font-weight:400; margin-left: 4px;"></span>
                                </label>
                                <select id="groups" name="group_ids[]" class="select2-multiple" multiple style="width: 100%;">
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 300px;">
                                <label class="filter-label" style="color:#475569;">
                                    Og'irlik koeffitsientlari (JN+MT+ON+OSKI+Test = 100%)
                                </label>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                    <div class="weight-item">
                                        <span class="weight-label">JN</span>
                                        <input type="number" name="weight_jn" id="weight_jn" value="50" min="0" max="100" class="weight-input">
                                        <span class="weight-pct">%</span>
                                    </div>
                                    <div class="weight-item">
                                        <span class="weight-label">MT</span>
                                        <input type="number" name="weight_mt" id="weight_mt" value="20" min="0" max="100" class="weight-input">
                                        <span class="weight-pct">%</span>
                                    </div>
                                    <div class="weight-item">
                                        <span class="weight-label">ON</span>
                                        <input type="number" name="weight_on" id="weight_on" value="0" min="0" max="100" class="weight-input">
                                        <span class="weight-pct">%</span>
                                    </div>
                                    <div class="weight-item">
                                        <span class="weight-label">OSKI</span>
                                        <input type="number" name="weight_oski" id="weight_oski" value="0" min="0" max="100" class="weight-input">
                                        <span class="weight-pct">%</span>
                                    </div>
                                    <div class="weight-item">
                                        <span class="weight-label">Test</span>
                                        <input type="number" name="weight_test" id="weight_test" value="30" min="0" max="100" class="weight-input">
                                        <span class="weight-pct">%</span>
                                    </div>
                                    <div id="weight-sum-indicator" class="weight-sum">100%</div>
                                </div>
                            </div>

                            <div style="display: flex; align-items: flex-end; padding-bottom: 6px; gap: 8px;">
                                <div id="export-loading" style="display: none; align-items: center; color: #2b5ea7;">
                                    <svg class="animate-spin" style="height: 16px; width: 16px; margin-right: 4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <button type="submit" id="export-btn" class="export-btn" disabled>
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Excel yuklab olish
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div id="subject-info" style="display:none; padding: 10px 20px; background:#f0f9ff; border-bottom: 1px solid #bae6fd; font-size:13px; color:#0369a1;">
                        <span id="subject-info-text"></span>
                    </div>
                </form>

                <!-- Help text -->
                <div style="padding: 40px 20px; text-align: center; color: #94a3b8;">
                    <svg style="width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p style="font-size:14px;">Fan va guruhlarni tanlab, "Excel yuklab olish" tugmasini bosing.</p>
                    <p style="font-size:12px; margin-top:4px;">Bir vaqtda bir nechta guruhni tanlash mumkin.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const sidebarUrl = '{{ route("admin.journal.get-sidebar-options") }}';

        function stripSpecialChars(str) {
            return str.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase();
        }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            var sc = stripSpecialChars(params.term);
            var oc = stripSpecialChars(data.text);
            if (oc.indexOf(sc) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        $(document).ready(function () {

            // Init select2 singles
            ['#faculty', '#specialty', '#level_code', '#semester_code', '#subject'].forEach(function(sel) {
                $(sel).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(sel).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() {
                        var sf = document.querySelector('.select2-container--open .select2-search__field');
                        if (sf) sf.focus();
                    }, 10);
                });
            });

            // Init select2 multiple for groups
            $('#groups').select2({
                theme: 'classic',
                width: '100%',
                placeholder: 'Guruhlarni tanlang',
                matcher: fuzzyMatcher,
                closeOnSelect: false
            }).on('change', function() {
                var count = $(this).val() ? $(this).val().length : 0;
                $('#groups-count').text(count > 0 ? '(' + count + ' ta tanlangan)' : '');
                updateExportBtn();
            });

            function getParams() {
                return {
                    faculty_id: $('#faculty').val() || '',
                    specialty_id: $('#specialty').val() || '',
                    level_code: $('#level_code').val() || '',
                    semester_code: $('#semester_code').val() || '',
                    subject_id: $('#subject').val() || '',
                };
            }

            function resetEl(sel, ph) {
                $(sel).empty().append('<option value="">' + ph + '</option>');
                if ($(sel).hasClass('select2-hidden-accessible')) {
                    $(sel).trigger('change.select2');
                }
            }

            function populateSelect(sel, data, ph) {
                resetEl(sel, ph || 'Barchasi');
                $.each(data, function(k, v) {
                    $(sel).append('<option value="' + k + '">' + v + '</option>');
                });
                if ($(sel).hasClass('select2-hidden-accessible')) {
                    $(sel).trigger('change.select2');
                }
            }

            function populateGroups(data) {
                $('#groups').empty();
                $.each(data, function(k, v) {
                    $('#groups').append('<option value="' + k + '">' + v + '</option>');
                });
                $('#groups').trigger('change.select2');
                updateExportBtn();
            }

            function refreshAll() {
                var p = getParams();
                $.ajax({
                    url: sidebarUrl,
                    type: 'GET',
                    data: p,
                    success: function(d) {
                        // Faculties
                        populateSelect('#faculty', d.faculties, 'Barchasi');
                        if (p.faculty_id) $('#faculty').val(p.faculty_id).trigger('change.select2');
                        // Specialties
                        populateSelect('#specialty', d.specialties, 'Barchasi');
                        if (p.specialty_id) $('#specialty').val(p.specialty_id).trigger('change.select2');
                        // Levels
                        populateSelect('#level_code', d.levels, 'Barchasi');
                        if (p.level_code) $('#level_code').val(p.level_code).trigger('change.select2');
                        // Semesters
                        populateSelect('#semester_code', d.semesters, 'Barchasi');
                        if (p.semester_code) $('#semester_code').val(p.semester_code).trigger('change.select2');
                        // Subjects
                        var curSubject = $('#subject').val();
                        populateSelect('#subject', d.subjects, 'Tanlang');
                        if (curSubject && d.subjects[curSubject]) $('#subject').val(curSubject).trigger('change.select2');
                        // Groups
                        populateGroups(d.groups);

                        // Subject info
                        if (p.subject_id && d.kafedra_name) {
                            $('#subject-info-text').text('Kafedra: ' + d.kafedra_name);
                            $('#subject-info').show();
                        } else {
                            $('#subject-info').hide();
                        }
                    }
                });
            }

            function updateExportBtn() {
                var hasSubject = !!$('#subject').val();
                var hasSemester = !!$('#semester_code').val();
                var hasGroups = $('#groups').val() && $('#groups').val().length > 0;
                var weightOk = checkWeightSum();
                var canExport = hasSubject && hasSemester && hasGroups && weightOk;
                $('#export-btn').prop('disabled', !canExport);
                if (canExport) {
                    $('#export-btn').addClass('export-btn-active');
                } else {
                    $('#export-btn').removeClass('export-btn-active');
                }
            }

            function checkWeightSum() {
                var sum = parseInt($('#weight_jn').val() || 0)
                        + parseInt($('#weight_mt').val() || 0)
                        + parseInt($('#weight_on').val() || 0)
                        + parseInt($('#weight_oski').val() || 0)
                        + parseInt($('#weight_test').val() || 0);
                var ok = sum === 100;
                $('#weight-sum-indicator').text(sum + '%');
                $('#weight-sum-indicator').css('color', ok ? '#16a34a' : '#dc2626');
                return ok;
            }

            // Change handlers
            $('#faculty').on('change', function() {
                refreshAll();
            });
            $('#specialty').on('change', function() {
                var p = getParams();
                $.ajax({ url: sidebarUrl, type: 'GET', data: p, success: function(d) {
                    populateSelect('#level_code', d.levels, 'Barchasi');
                    populateSelect('#semester_code', d.semesters, 'Barchasi');
                    populateSelect('#subject', d.subjects, 'Tanlang');
                    populateGroups(d.groups);
                }});
            });
            $('#level_code').on('change', function() {
                var p = getParams();
                $.ajax({ url: sidebarUrl, type: 'GET', data: p, success: function(d) {
                    populateSelect('#semester_code', d.semesters, 'Barchasi');
                    populateSelect('#subject', d.subjects, 'Tanlang');
                    populateGroups(d.groups);
                }});
            });
            $('#semester_code').on('change', function() {
                var val = $(this).val();
                $('#hidden_semester_code').val(val);
                var p = getParams();
                $.ajax({ url: sidebarUrl, type: 'GET', data: p, success: function(d) {
                    var curSubject = $('#subject').val();
                    populateSelect('#subject', d.subjects, 'Tanlang');
                    if (curSubject && d.subjects[curSubject]) $('#subject').val(curSubject).trigger('change.select2');
                    populateGroups(d.groups);
                    updateExportBtn();
                }});
            });
            $('#subject').on('change', function() {
                var val = $(this).val();
                $('#hidden_subject_id').val(val);
                var p = getParams();
                $.ajax({ url: sidebarUrl, type: 'GET', data: p, success: function(d) {
                    populateGroups(d.groups);
                    if (val && d.kafedra_name) {
                        $('#subject-info-text').text('Kafedra: ' + d.kafedra_name);
                        $('#subject-info').show();
                    } else {
                        $('#subject-info').hide();
                    }
                    updateExportBtn();
                }});
            });

            // Weight inputs
            $('.weight-input').on('input', function() {
                checkWeightSum();
                updateExportBtn();
            });

            // Export form submit
            $('#export-form').on('submit', function(e) {
                if ($('#export-btn').prop('disabled')) {
                    e.preventDefault();
                    return;
                }
                $('#export-loading').css('display', 'flex');
                $('#export-btn').prop('disabled', true).text('Yuklanmoqda...');
                setTimeout(function() {
                    $('#export-loading').hide();
                    $('#export-btn').prop('disabled', false).html(
                        '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>' +
                        '</svg> Excel yuklab olish'
                    );
                    updateExportBtn();
                }, 5000);
            });

            // Initial load
            refreshAll();
            checkWeightSum();
        });
    </script>

    <style>
        .filter-container {
            padding: 16px 20px 12px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf5 100%);
            border-bottom: 2px solid #dbe4ef;
        }
        .filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            align-items: flex-end;
        }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }
        .fl-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .select2-container--classic .select2-selection--single {
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px;
            padding-left: 10px;
            padding-right: 52px;
            color: #1e293b;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 34px;
            width: 22px;
            background: transparent;
            border-left: none;
        }
        .select2-container--classic .select2-selection--multiple {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            min-height: 36px;
        }
        .weight-item {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .weight-label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            min-width: 30px;
        }
        .weight-input {
            width: 46px;
            height: 30px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            padding: 0 4px;
            outline: none;
        }
        .weight-input:focus {
            border-color: #2b5ea7;
            box-shadow: 0 0 0 2px rgba(43,94,167,0.12);
        }
        .weight-pct {
            font-size: 11px;
            color: #64748b;
        }
        .weight-sum {
            font-size: 13px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            background: #f1f5f9;
            margin-left: 4px;
        }
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: #94a3b8;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: not-allowed;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .export-btn.export-btn-active {
            background: #2b5ea7;
            cursor: pointer;
        }
        .export-btn.export-btn-active:hover {
            background: #1e4080;
        }
    </style>
</x-app-layout>
