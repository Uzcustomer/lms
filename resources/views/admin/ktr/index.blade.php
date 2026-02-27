<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            KTR (Kalendar tematik reja)
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="overflow: visible;">

                <!-- Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.ktr.index') }}">
                    <div class="filter-container">

                        <!-- Row 1 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label fl-blue">
                                    <span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi
                                </label>
                                <select name="education_type" id="education_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? request('education_type')) == $type->education_type_code ? 'selected' : '' }}>
                                            {{ $type->education_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label fl-emerald">
                                    <span class="fl-dot" style="background:#10b981;"></span> Fakultet
                                </label>
                                <select name="faculty" id="faculty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                            {{ $faculty->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 240px;">
                                <label class="filter-label fl-cyan">
                                    <span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish
                                </label>
                                <select name="specialty" id="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label">&nbsp;</label>
                                <input type="hidden" name="current_semester" id="current_semester_input" value="{{ request('current_semester', '1') }}">
                                <div class="toggle-switch {{ request('current_semester', '1') == '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleCurrentSemester()">
                                    <div class="toggle-track">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="toggle-label">Joriy semestr</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label fl-violet">
                                    <span class="fl-dot" style="background:#8b5cf6;"></span> Kurs
                                </label>
                                <select name="level_code" id="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 150px;">
                                <label class="filter-label fl-teal">
                                    <span class="fl-dot" style="background:#14b8a6;"></span> Semestr
                                </label>
                                <select name="semester_code" id="semester_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label" style="color: #ea580c;">
                                    <span class="fl-dot" style="background:#ea580c;"></span> Fan
                                </label>
                                <input type="text" name="subject_name" id="subject_name" value="{{ request('subject_name') }}"
                                       placeholder="Fan nomini kiriting..."
                                       style="height: 36px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 10px; font-size: 13px; outline: none; width: 100%;"
                                       onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
                            </div>

                            <div class="filter-item" style="min-width: 130px;">
                                <label class="filter-label fl-slate">
                                    <span class="fl-dot" style="background:#94a3b8;"></span> Holati
                                </label>
                                <select name="active_filter" id="active_filter" class="select2" style="width: 100%;">
                                    <option value="active" {{ request('active_filter', 'active') == 'active' ? 'selected' : '' }}>Faol</option>
                                    <option value="inactive" {{ request('active_filter') == 'inactive' ? 'selected' : '' }}>Nofaol</option>
                                    <option value="all" {{ request('active_filter') == 'all' ? 'selected' : '' }}>Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label fl-slate">
                                    <span class="fl-dot" style="background:#94a3b8;"></span> Sahifada
                                </label>
                                <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $pageSize)
                                        <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                            {{ $pageSize }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="display: flex; align-items: flex-end; padding-bottom: 6px; gap: 8px;">
                                <button type="submit" class="ktr-search-btn">
                                    <svg style="width: 16px; height: 16px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    Qidirish
                                </button>
                                <a href="{{ route('admin.ktr.export', request()->query()) }}" class="ktr-export-btn">
                                    <svg style="width: 16px; height: 16px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Excel
                                </a>
                                <div id="filter-loading" class="hidden" style="display: none; align-items: center; color: #2b5ea7;">
                                    <svg class="animate-spin" style="height: 16px; width: 16px; margin-right: 4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div style="max-height: calc(100vh - 300px); overflow-y: auto; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    @if($subjects->isEmpty())
                        <div style="padding: 60px 20px; text-align: center;">
                            <svg style="width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p style="color: #94a3b8; font-size: 14px;">Filtrlarni tanlang va "Qidirish" tugmasini bosing.</p>
                        </div>
                    @else
                        @php
                            $sortColumn = $sortColumn ?? 'faculty_name';
                            $sortDirection = $sortDirection ?? 'asc';
                        @endphp
                        <table class="journal-table">
                            <thead>
                                <tr>
                                    <th class="th-num">#</th>
                                    @php
                                        $columns = [
                                            'faculty_name' => 'Fakultet',
                                            'specialty_name' => "Yo'nalish",
                                            'level_name' => 'Kurs',
                                            'semester_name' => 'Semestr',
                                            'subject_name' => 'Fan',
                                            'credit' => 'Kredit',
                                            'total_acload' => 'Jami yuklama',
                                        ];
                                    @endphp
                                    @foreach($columns as $column => $label)
                                        @php
                                            $isActive = $sortColumn === $column;
                                            $newDirection = ($isActive && $sortDirection === 'asc') ? 'desc' : 'asc';
                                            $sortUrl = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $newDirection]);
                                        @endphp
                                        <th>
                                            <a href="{{ $sortUrl }}" class="sort-link">
                                                {{ $label }}
                                                @if($isActive)
                                                    <span class="sort-icon active">
                                                        @if($sortDirection === 'asc') &#9650; @else &#9660; @endif
                                                    </span>
                                                @else
                                                    <span class="sort-icon">&#9650;&#9660;</span>
                                                @endif
                                            </a>
                                        </th>
                                    @endforeach
                                    @foreach($trainingTypes as $code => $name)
                                        <th class="ktr-type-th">{{ $name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subjects as $index => $item)
                                    @php
                                        $details = $item->subject_details;
                                        if (is_string($details)) {
                                            $details = json_decode($details, true);
                                        }
                                        $loadMap = [];
                                        if (is_array($details)) {
                                            foreach ($details as $detail) {
                                                $tCode = (string) ($detail['trainingType']['code'] ?? '');
                                                $hours = (int) ($detail['academic_load'] ?? 0);
                                                if ($tCode !== '') {
                                                    $loadMap[$tCode] = $hours;
                                                }
                                            }
                                        }
                                    @endphp
                                    <tr class="journal-row" style="cursor: pointer;" onclick="openKtrPlan({{ $item->id }})">
                                        <td class="td-num">{{ $subjects->firstItem() + $index }}</td>
                                        <td><span class="text-cell text-emerald">{{ $item->faculty_name ?? '-' }}</span></td>
                                        <td><span class="text-cell text-cyan">{{ $item->specialty_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-violet">{{ $item->level_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-teal">{{ $item->semester_name ?? '-' }}</span></td>
                                        <td><span class="text-cell text-subject">{{ $item->subject_name ?? '-' }}</span></td>
                                        <td style="text-align: center;"><span class="badge badge-blue">{{ $item->credit ?? '-' }}</span></td>
                                        <td style="text-align: center;"><span class="badge badge-amber">{{ $item->total_acload ?? '-' }}</span></td>
                                        @foreach($trainingTypes as $code => $name)
                                            <td style="text-align: center;">
                                                @if(isset($loadMap[$code]) && $loadMap[$code] > 0)
                                                    <span class="badge badge-indigo">{{ $loadMap[$code] }}</span>
                                                @else
                                                    <span style="color: #cbd5e1;">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                            {{ $subjects->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- KTR Plan Modal -->
    <div id="ktr-modal-overlay" class="ktr-modal-overlay" style="display:none;" onclick="closeKtrModal(event)">
        <div class="ktr-modal" onclick="event.stopPropagation()">
            <div class="ktr-modal-header">
                <div>
                    <h3 class="ktr-modal-title" id="ktr-modal-title">Fan nomi</h3>
                    <div class="ktr-modal-subtitle">
                        Jami yuklama: <span class="badge badge-amber" id="ktr-total-load">0</span> soat
                    </div>
                </div>
                <button class="ktr-modal-close" onclick="closeKtrModal()">&times;</button>
            </div>

            <div class="ktr-modal-body">
                <!-- Hafta tanlash -->
                <div class="ktr-week-selector" id="ktr-week-selector">
                    <label class="filter-label fl-blue" style="margin-bottom: 8px;">
                        <span class="fl-dot" style="background:#3b82f6;"></span> Fan davomiyligi (hafta)
                    </label>
                    <div class="ktr-week-buttons" id="ktr-week-buttons">
                        @for($w = 1; $w <= 18; $w++)
                            <button type="button" class="ktr-week-btn" data-week="{{ $w }}" onclick="selectWeekCount({{ $w }})">{{ $w }}</button>
                        @endfor
                    </div>
                </div>

                <!-- Soatlar jadvali -->
                <div id="ktr-plan-table-wrap" style="display:none;">
                    <div style="max-height: 55vh; overflow: auto;">
                        <table class="ktr-plan-table" id="ktr-plan-table">
                            <thead id="ktr-plan-thead"></thead>
                            <tbody id="ktr-plan-tbody"></tbody>
                            <tfoot id="ktr-plan-tfoot"></tfoot>
                        </table>
                    </div>

                    <!-- Xabarnoma -->
                    <div id="ktr-validation-msg" class="ktr-validation-msg" style="display:none;"></div>

                    <!-- O'zgartirish so'rovi paneli -->
                    <div id="ktr-change-panel" style="display:none; margin-top: 12px;"></div>

                    <!-- Tugmalar -->
                    <div style="display: flex; justify-content: space-between; margin-top: 16px; align-items: center;">
                        <a id="ktr-word-export-btn" href="#" class="ktr-btn ktr-btn-export" target="_blank" style="text-decoration:none; display:none;">
                            <svg style="width: 15px; height: 15px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Word
                        </a>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="ktr-btn ktr-btn-edit" id="ktr-edit-btn" onclick="onKtrEditClick()" style="display:none;">
                                <svg style="width: 15px; height: 15px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                O'zgartirish
                            </button>
                            <button type="button" class="ktr-btn ktr-btn-secondary" onclick="closeKtrModal()">Yopish</button>
                            <button type="button" class="ktr-btn ktr-btn-primary" id="ktr-save-btn" onclick="saveKtrPlan()" style="display:none;">Saqlash</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div id="ktr-modal-loading" class="ktr-modal-loading" style="display:none;">
                <svg class="animate-spin" style="height: 32px; width: 32px; color: #3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function stripSpecialChars(str) {
            return str.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase();
        }

        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            var searchClean = stripSpecialChars(params.term);
            var optionClean = stripSpecialChars(data.text);
            if (optionClean.indexOf(searchClean) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function toggleCurrentSemester() {
            const btn = document.getElementById('current-semester-toggle');
            const input = document.getElementById('current_semester_input');
            const isActive = btn.classList.contains('active');
            if (isActive) {
                btn.classList.remove('active');
                input.value = '0';
            } else {
                btn.classList.add('active');
                input.value = '1';
            }
        }

        $(document).ready(function () {
            $('.select2').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() {
                        var sf = document.querySelector('.select2-container--open .select2-search__field');
                        if (sf) sf.focus();
                    }, 10);
                });
            });

            const selectedSpecialty = @json(request('specialty'));
            const selectedLevelCode = @json(request('level_code'));
            const selectedSemesterCode = @json(request('semester_code'));

            function resetDropdown(el, ph) {
                $(el).empty().append('<option value="">' + ph + '</option>');
            }

            function populateDropdown(url, params, element, callback) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    $.each(data, function (k, v) { $(element).append('<option value="' + k + '">' + v + '</option>'); });
                    if (callback) callback(data);
                }});
            }

            function populateDropdownUnique(url, params, element, callback) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    var unique = {};
                    $.each(data, function (k, v) { if (!unique[v]) unique[v] = k; });
                    $.each(unique, function (n, k) { $(element).append('<option value="' + k + '">' + n + '</option>'); });
                    if (callback) callback(data);
                }});
            }

            function getFilterParams() {
                return {
                    education_type: $('#education_type').val() || '',
                    faculty_id: $('#faculty').val() || '',
                    current_semester: $('#current_semester_input').val() || '1',
                };
            }

            function refreshSpecialties() {
                resetDropdown('#specialty', 'Barchasi');
                populateDropdownUnique('{{ route("admin.ktr.get-specialties") }}', getFilterParams(), '#specialty');
            }

            $('#education_type').change(function () { refreshSpecialties(); });
            $('#faculty').change(function () { refreshSpecialties(); });
            $('#per_page').on('change', function() {
                $('#filter-loading').removeClass('hidden').css('display', 'flex');
                $('#filter-form').submit();
            });

            $('#level_code').change(function () {
                var lc = $(this).val();
                resetDropdown('#semester_code', 'Barchasi');
                if (lc) {
                    populateDropdown('{{ route("admin.ktr.get-semesters") }}', { level_code: lc }, '#semester_code');
                }
            });

            // Sahifa yuklanganda filtrlarni to'ldirish
            var p = getFilterParams();
            populateDropdownUnique('{{ route("admin.ktr.get-specialties") }}', p, '#specialty', function() {
                if (selectedSpecialty) $('#specialty').val(selectedSpecialty).trigger('change.select2');
            });
            populateDropdown('{{ route("admin.ktr.get-level-codes") }}', {}, '#level_code', function() {
                if (selectedLevelCode) {
                    $('#level_code').val(selectedLevelCode).trigger('change.select2');
                    // Semestr ham yuklash
                    if (selectedLevelCode) {
                        populateDropdown('{{ route("admin.ktr.get-semesters") }}', { level_code: selectedLevelCode }, '#semester_code', function() {
                            if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change.select2');
                        });
                    }
                }
            });
        });

        // ===== KTR Plan Modal =====
        var ktrState = {
            csId: null,
            totalLoad: 0,
            trainingTypes: {},
            filteredCodes: [],
            weekCount: 0,
            savedHours: {},
            savedTopics: {},
            hemisTopics: {},
            canEdit: false,
            editMode: false,
            hasPlan: false,
            approverInfo: {},
            changeRequest: null
        };

        // Mustaqil ta'limni filtrdan chiqarish
        function isMustaqil(name) {
            return /mustaqil/i.test(name.replace(/[^a-zA-Z\u0400-\u04FF]/g, ''));
        }

        // Mashg'ulot turi tartibini aniqlash (Ma'ruza, Amaliy, Laboratoriya, ...)
        var ktrTypeOrder = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar'];
        function getTypePosition(name) {
            var normalized = name.replace(/[^a-zA-Z\u0400-\u04FF]/g, '').toLowerCase();
            for (var i = 0; i < ktrTypeOrder.length; i++) {
                if (normalized.indexOf(ktrTypeOrder[i]) > -1) return i;
            }
            return ktrTypeOrder.length;
        }

        function openKtrPlan(csId) {
            ktrState.csId = csId;
            $('#ktr-modal-overlay').fadeIn(200);
            $('#ktr-modal-loading').show();
            $('#ktr-week-selector').hide();
            $('#ktr-plan-table-wrap').hide();
            $('#ktr-validation-msg').hide();

            $.ajax({
                url: '/admin/ktr/plan/' + csId,
                type: 'GET',
                success: function(data) {
                    $('#ktr-modal-loading').hide();
                    $('#ktr-modal-title').text(data.subject_name);
                    $('#ktr-total-load').text(data.total_acload);
                    ktrState.totalLoad = data.total_acload;
                    ktrState.trainingTypes = data.training_types;

                    // Mustaqil ta'limni chiqarib tashlash va to'g'ri tartibda saralash
                    ktrState.filteredCodes = Object.keys(data.training_types)
                        .filter(function(code) {
                            return !isMustaqil(data.training_types[code].name);
                        })
                        .sort(function(a, b) {
                            return getTypePosition(data.training_types[a].name) - getTypePosition(data.training_types[b].name);
                        });

                    ktrState.savedHours = {};
                    ktrState.savedTopics = {};
                    ktrState.hemisTopics = data.hemis_topics || {};
                    ktrState.canEdit = data.can_edit || false;
                    ktrState.hasPlan = !!(data.plan && data.plan.week_count);
                    ktrState.approverInfo = data.approver_info || {};

                    // Word eksport linkini yangilash (faqat reja bo'lsa ko'rsatish)
                    $('#ktr-word-export-btn').attr('href', '/admin/ktr/export-word/' + csId);
                    if (ktrState.hasPlan) {
                        $('#ktr-word-export-btn').show();
                    } else {
                        $('#ktr-word-export-btn').hide();
                    }
                    ktrState.changeRequest = data.change_request || null;

                    // Saqlangan ma'lumotlarni yuklash
                    if (data.plan && data.plan.plan_data) {
                        var pd = data.plan.plan_data;
                        if (pd.hours) {
                            ktrState.savedHours = pd.hours;
                            ktrState.savedTopics = pd.topics || {};
                        } else {
                            ktrState.savedHours = pd;
                        }
                    }

                    $('.ktr-week-btn').removeClass('active');
                    $('#ktr-change-panel').hide().html('');

                    if (ktrState.hasPlan) {
                        // Saqlangan reja bor - ko'rish rejimida ochiladi
                        ktrState.editMode = false;
                        $('#ktr-week-selector').hide();
                        $('#ktr-save-btn').hide();
                        $('#ktr-edit-btn').toggle(ktrState.canEdit);
                        selectWeekCount(data.plan.week_count, true);
                        // Agar faol o'zgartirish so'rovi bo'lsa, panelni ko'rsatish
                        if (ktrState.changeRequest) {
                            renderChangePanel();
                        }
                    } else if (ktrState.canEdit) {
                        // Reja yo'q, tahrirlash huquqi bor - darhol tahrirlash rejimi
                        ktrState.editMode = true;
                        $('#ktr-week-selector').show();
                        $('#ktr-save-btn').show();
                        $('#ktr-edit-btn').hide();
                    } else {
                        // Reja yo'q, huquq yo'q
                        ktrState.editMode = false;
                        $('#ktr-week-selector').hide();
                        $('#ktr-save-btn').hide();
                        $('#ktr-edit-btn').hide();
                        $('#ktr-plan-table-wrap').hide();
                        $('#ktr-validation-msg')
                            .html('Bu fan uchun hali KTR rejasi tuzilmagan.')
                            .removeClass('ktr-msg-error').addClass('ktr-msg-success').show();
                    }
                },
                error: function(xhr) {
                    $('#ktr-modal-loading').hide();
                    var msg = "Ma'lumotlarni yuklashda xatolik yuz berdi";
                    if (xhr.responseJSON && xhr.responseJSON.message) msg += ': ' + xhr.responseJSON.message;
                    alert(msg);
                }
            });
        }

        function closeKtrModal(event) {
            if (event && event.target !== document.getElementById('ktr-modal-overlay')) return;
            $('#ktr-modal-overlay').fadeOut(200);
        }

        function onKtrEditClick() {
            var cr = ktrState.changeRequest;
            // So'rov allaqachon jo'natilgan (draft saqlangan) - panelni ko'rsatish
            if (cr && !cr.is_approved) {
                renderChangePanel();
                return;
            }
            // So'rov yo'q - ogohlantirish ko'rsatish va tahrirlashga ruxsat berish
            showEditWarningAndEnable();
        }

        function enableKtrEdit() {
            ktrState.editMode = true;
            $('#ktr-edit-btn').hide();
            $('#ktr-save-btn').show();
            $('#ktr-week-selector').show();
            $('#ktr-change-panel').hide();
            $('.ktr-hours').prop('readonly', false).prop('disabled', false);
            $('#ktr-validation-msg').hide();
        }

        function getRoleName(role) {
            var names = {
                'kafedra_mudiri': 'Kafedra mudiri',
                'dekan': 'Dekan',
                'registrator_ofisi': 'Registrator ofisi'
            };
            return names[role] || role;
        }

        function getStatusBadge(status) {
            if (status === 'approved') return '<span class="ktr-approval-badge ktr-approved">Tasdiqlandi</span>';
            if (status === 'rejected') return '<span class="ktr-approval-badge ktr-rejected">Rad etildi</span>';
            return '<span class="ktr-approval-badge ktr-pending">Kutilmoqda</span>';
        }

        function showEditWarningAndEnable() {
            var info = ktrState.approverInfo;
            var html = '<div class="ktr-change-box" style="border-left: 4px solid #f59e0b; background: #fffbeb;">';
            html += '<div class="ktr-change-title" style="color:#b45309;"><svg style="width:18px;height:18px;vertical-align:middle;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>Diqqat!</div>';
            html += '<div class="ktr-change-desc" style="margin-bottom:10px;">O\'zgartirgan KTRingizni saqlash uchun quyidagilardan ruxsat so\'rashingiz kerak:</div>';
            if (info.kafedra_name || info.faculty_name) {
                html += '<div style="margin-bottom:8px; font-size:13px; color:#374151;">';
                if (info.faculty_name) html += '<div><b>Fakultet:</b> ' + info.faculty_name + '</div>';
                if (info.kafedra_name) html += '<div><b>Kafedra:</b> ' + info.kafedra_name + '</div>';
                html += '</div>';
            }
            html += '<ul class="ktr-approver-list">';
            html += '<li><b>Kafedra mudiri</b>: ' + (info.kafedra_mudiri ? info.kafedra_mudiri.name : 'Topilmadi') + '</li>';
            html += '<li><b>Dekan</b>: ' + (info.dekan ? info.dekan.name : 'Topilmadi') + '</li>';
            html += '<li><b>Registrator ofisi</b></li>';
            html += '</ul>';
            html += '<div class="ktr-change-desc" style="font-size:12px; color:#6b7280; margin-bottom:10px;">Tahrirlashdan so\'ng "Saqlash" tugmasini bossangiz, o\'zgarishlar draft sifatida saqlanadi va tasdiqlash so\'rovi yuboriladi. Tasdiqlangandan keyin KTR yangilanadi.</div>';
            html += '<button type="button" class="ktr-btn ktr-btn-primary" onclick="confirmAndEnableEdit()">Tushundim, davom etish</button>';
            html += '</div>';
            $('#ktr-change-panel').html(html).show();
        }

        function confirmAndEnableEdit() {
            $('#ktr-change-panel').hide().html('');
            enableKtrEdit();
        }

        function renderChangePanel() {
            var cr = ktrState.changeRequest;
            if (!cr) return;
            var html = '<div class="ktr-change-box">';
            if (cr.is_approved) {
                html += '<div class="ktr-change-title" style="color:#059669;">Barcha tasdiqlar olingan! KTR yangilandi.</div>';
            } else {
                html += '<div class="ktr-change-title">Tasdiqlash kutilmoqda (draft saqlangan)</div>';
                html += '<div class="ktr-change-desc" style="font-size:12px; color:#6b7280;">O\'zgarishlar draft sifatida saqlangan. Barcha tasdiqlar olingandan keyin KTR yangilanadi.</div>';
            }

            // O'zgarishlar diffini ko'rsatish
            if (cr.draft_plan_data && ktrState.savedHours) {
                html += renderDraftDiff(cr);
            }

            var info = ktrState.approverInfo;
            html += '<table class="ktr-approval-table"><thead><tr><th>Lavozim</th><th>Ism</th><th>Bo\'lim</th><th>Holat</th><th>Sana</th></tr></thead><tbody>';
            cr.approvals.forEach(function(a) {
                var bolim = '-';
                if (a.role === 'kafedra_mudiri') {
                    bolim = info.kafedra_name || '-';
                } else if (a.role === 'dekan') {
                    bolim = info.faculty_name ? (info.faculty_name + ' fakulteti') : '-';
                }
                html += '<tr>';
                html += '<td>' + getRoleName(a.role) + '</td>';
                html += '<td>' + a.approver_name + '</td>';
                html += '<td>' + bolim + '</td>';
                html += '<td>' + getStatusBadge(a.status) + '</td>';
                html += '<td>' + (a.responded_at || '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '</div>';
            $('#ktr-change-panel').html(html).show();
            $('#ktr-edit-btn').hide();
        }

        function renderDraftDiff(cr) {
            var oldHours = ktrState.savedHours;
            var newData = cr.draft_plan_data;
            var newHours = newData.hours || newData;
            var types = ktrState.trainingTypes;
            var codes = ktrState.filteredCodes;
            var changes = [];

            // Hafta soni tekshirish
            if (ktrState.hasPlan && cr.draft_week_count) {
                var oldWeekCount = Object.keys(oldHours).length;
                if (oldWeekCount != cr.draft_week_count) {
                    changes.push('<b>Hafta soni:</b> ' + oldWeekCount + ' → ' + cr.draft_week_count);
                }
            }

            // Har bir hafta va tur bo'yicha soatlar farqi
            var allWeeks = [];
            for (var k in oldHours) allWeeks.push(k);
            for (var k in newHours) { if (allWeeks.indexOf(k) === -1) allWeeks.push(k); }
            allWeeks.sort(function(a,b) { return parseInt(a) - parseInt(b); });

            allWeeks.forEach(function(week) {
                var oldW = oldHours[week] || {};
                var newW = newHours[week] || {};
                codes.forEach(function(code) {
                    var oldVal = parseInt(oldW[code]) || 0;
                    var newVal = parseInt(newW[code]) || 0;
                    if (oldVal !== newVal) {
                        var typeName = types[code] ? types[code].name : code;
                        changes.push(week + '-hafta <b>' + typeName + '</b>: <span style="color:#dc2626;text-decoration:line-through;">' + oldVal + '</span> → <span style="color:#059669;font-weight:600;">' + newVal + '</span> soat');
                    }
                });
            });

            if (changes.length === 0) return '';

            var html = '<div style="margin:10px 0; padding:10px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px;">';
            html += '<div style="font-weight:600; margin-bottom:6px; color:#0369a1;">O\'zgarishlar:</div>';
            html += '<ul style="margin:0; padding-left:18px; font-size:13px; line-height:1.8;">';
            var maxShow = Math.min(changes.length, 15);
            for (var i = 0; i < maxShow; i++) {
                html += '<li>' + changes[i] + '</li>';
            }
            if (changes.length > 15) {
                html += '<li style="color:#6b7280;">... va yana ' + (changes.length - 15) + ' ta o\'zgarish</li>';
            }
            html += '</ul></div>';
            return html;
        }

        function sendChangeRequest() {
            $('#ktr-request-btn').prop('disabled', true).text('Jo\'natilmoqda...');
            $.ajax({
                url: '/admin/ktr/change-request/' + ktrState.csId,
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(resp) {
                    if (resp.success) {
                        ktrState.changeRequest = resp.change_request;
                        renderChangePanel();
                        $('#ktr-edit-btn').hide();
                    }
                },
                error: function(xhr) {
                    var msg = 'Xatolik yuz berdi';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    $('#ktr-request-btn').prop('disabled', false).text('Ruxsat so\'rash');
                    alert(msg);
                }
            });
        }

        function selectWeekCount(count, fromLoad) {
            ktrState.weekCount = count;
            $('.ktr-week-btn').removeClass('active');
            $('.ktr-week-btn[data-week="' + count + '"]').addClass('active');
            buildPlanTable(count, fromLoad);
            $('#ktr-plan-table-wrap').slideDown(200);
        }

        // Hemis dan kelgan mavzuni olish (topicIndex = 0-based ketma-ket tartib raqami)
        function getHemisTopic(code, topicIndex) {
            var topics = ktrState.hemisTopics[code];
            if (!topics || !topics.length) return '';
            if (topicIndex < topics.length) return topics[topicIndex].name;
            return '';
        }

        function buildPlanTable(weekCount, fromLoad) {
            var types = ktrState.trainingTypes;
            var codes = ktrState.filteredCodes;

            // Thead: Hafta | Mavzu | [Soat per type]
            var thead = '<tr><th class="ktr-th-week" rowspan="2">Hafta</th>';
            thead += '<th class="ktr-th-topic-combined" rowspan="2">Mavzu</th>';
            codes.forEach(function(code) {
                thead += '<th class="ktr-th-type">' + types[code].name + '<div class="ktr-th-hours">' + types[code].hours + ' soat</div></th>';
            });
            thead += '</tr><tr>';
            codes.forEach(function() {
                thead += '<th class="ktr-th-sub">Soat</th>';
            });
            thead += '</tr>';
            $('#ktr-plan-thead').html(thead);

            // Tbody
            var tbody = '';
            for (var w = 1; w <= weekCount; w++) {
                tbody += '<tr>';
                tbody += '<td class="ktr-td-week">' + w + '</td>';
                tbody += '<td class="ktr-td-topic" id="ktr-topic-cell-' + w + '"><span class="ktr-topic-text ktr-topic-hidden" id="ktr-topic-' + w + '"></span></td>';
                codes.forEach(function(code) {
                    var hrs = '';
                    if (fromLoad && ktrState.savedHours[w] && ktrState.savedHours[w][code] !== undefined) {
                        hrs = ktrState.savedHours[w][code];
                    }
                    var readonlyAttr = ktrState.editMode ? '' : ' readonly disabled';
                    tbody += '<td class="ktr-td-hrs"><input type="number" min="0" class="ktr-cell ktr-hours" data-week="' + w + '" data-code="' + code + '" value="' + hrs + '"' + readonlyAttr + '></td>';
                });
                tbody += '</tr>';
            }
            $('#ktr-plan-tbody').html(tbody);

            // Tfoot
            var tfoot = '<tr class="ktr-tfoot-sum"><td class="ktr-td-week" style="font-weight:700;">Jami</td>';
            tfoot += '<td class="ktr-td-topic-empty"></td>';
            codes.forEach(function(code) {
                tfoot += '<td class="ktr-td-sum" id="ktr-col-sum-' + code + '">0</td>';
            });
            tfoot += '</tr>';
            tfoot += '<tr class="ktr-tfoot-load"><td class="ktr-td-week" style="font-weight:700;">Yukl.</td>';
            tfoot += '<td class="ktr-td-topic-empty"></td>';
            codes.forEach(function(code) {
                tfoot += '<td class="ktr-td-load">' + types[code].hours + '</td>';
            });
            tfoot += '</tr>';
            $('#ktr-plan-tfoot').html(tfoot);

            // Event handlers
            $('#ktr-plan-table').off('input', '.ktr-hours').on('input', '.ktr-hours', function() {
                recalcTopics();
                recalcKtr();
            });

            // Keyboard navigation (Excel-like) - faqat soat inputlari
            $('#ktr-plan-table').off('keydown', '.ktr-hours').on('keydown', '.ktr-hours', function(e) {
                var key = e.which;
                if ([13, 9, 37, 38, 39, 40].indexOf(key) === -1) return;

                var $this = $(this);
                var week = parseInt($this.data('week'));
                var code = $this.data('code');
                var $target = null;
                var cIdx = codes.indexOf(String(code));

                if (key === 13 || key === 40) { // Enter / Down
                    e.preventDefault();
                    var nextW = week + 1;
                    if (nextW > ktrState.weekCount) nextW = 1;
                    $target = $('.ktr-hours[data-week="' + nextW + '"][data-code="' + code + '"]');
                } else if (key === 38) { // Up
                    e.preventDefault();
                    var prevW = week - 1;
                    if (prevW < 1) prevW = ktrState.weekCount;
                    $target = $('.ktr-hours[data-week="' + prevW + '"][data-code="' + code + '"]');
                } else if (key === 39 || (key === 9 && !e.shiftKey)) { // Right / Tab
                    e.preventDefault();
                    var nIdx = cIdx + 1;
                    if (nIdx < codes.length) $target = $('.ktr-hours[data-week="' + week + '"][data-code="' + codes[nIdx] + '"]');
                    else $target = $('.ktr-hours[data-week="' + (week < ktrState.weekCount ? week + 1 : 1) + '"][data-code="' + codes[0] + '"]');
                } else if (key === 37 || (key === 9 && e.shiftKey)) { // Left / Shift+Tab
                    e.preventDefault();
                    var pIdx = cIdx - 1;
                    if (pIdx >= 0) $target = $('.ktr-hours[data-week="' + week + '"][data-code="' + codes[pIdx] + '"]');
                    else $target = $('.ktr-hours[data-week="' + (week > 1 ? week - 1 : ktrState.weekCount) + '"][data-code="' + codes[codes.length - 1] + '"]');
                }

                if ($target && $target.length) $target.focus().select();
            });

            recalcTopics();
            recalcKtr();
        }

        // Mavzularni qayta hisoblash (ketma-ket tartib raqami bo'yicha)
        function recalcTopics() {
            var codes = ktrState.filteredCodes;
            var types = ktrState.trainingTypes;

            // Har bir code uchun: qaysi haftalarda soat > 0, ketma-ket raqamlash
            var topicIndexMap = {}; // topicIndexMap[code][week] = 0-based index
            codes.forEach(function(code) {
                topicIndexMap[code] = {};
                var seq = 0;
                for (var w = 1; w <= ktrState.weekCount; w++) {
                    var val = parseInt($('.ktr-hours[data-week="' + w + '"][data-code="' + code + '"]').val()) || 0;
                    if (val > 0) {
                        topicIndexMap[code][w] = seq;
                        seq++;
                    }
                }
            });

            // Har bir hafta uchun birlashtirilgan mavzu matnini yaratish
            for (var w = 1; w <= ktrState.weekCount; w++) {
                var parts = [];
                codes.forEach(function(code) {
                    if (topicIndexMap[code][w] !== undefined) {
                        var topicName = getHemisTopic(code, topicIndexMap[code][w]);
                        if (topicName) {
                            parts.push('<b>' + types[code].name + '</b>: ' + topicName.replace(/</g, '&lt;'));
                        }
                    }
                });
                var $cell = $('#ktr-topic-' + w);
                if (parts.length > 0) {
                    $cell.html(parts.join('<br>')).removeClass('ktr-topic-hidden');
                } else {
                    $cell.html('').addClass('ktr-topic-hidden');
                }
            }
        }

        function recalcKtr() {
            var types = ktrState.trainingTypes;
            var codes = ktrState.filteredCodes;
            var grandTotal = 0;
            var allMatch = true;

            codes.forEach(function(code) {
                var colSum = 0;
                $('.ktr-hours[data-code="' + code + '"]').each(function() {
                    colSum += parseInt($(this).val()) || 0;
                });
                $('#ktr-col-sum-' + code).text(colSum);
                grandTotal += colSum;

                var expected = types[code].hours;
                if (colSum !== expected) {
                    $('#ktr-col-sum-' + code).css('color', '#dc2626');
                    allMatch = false;
                } else {
                    $('#ktr-col-sum-' + code).css('color', '#059669');
                }
            });

            // Mustaqil ta'lim soatlarini ham jamiga qo'shish
            var mustaqilTotal = 0;
            Object.keys(types).forEach(function(code) {
                if (isMustaqil(types[code].name)) mustaqilTotal += types[code].hours;
            });
            grandTotal += mustaqilTotal;

            var $msg = $('#ktr-validation-msg');
            if (grandTotal > 0 && grandTotal !== ktrState.totalLoad) {
                var diff = ktrState.totalLoad - grandTotal;
                $msg.html('Jami soatlar mos kelmadi! Kiritilgan: <b>' + grandTotal + '</b>, Jami yuklama: <b>' + ktrState.totalLoad + '</b>' + (mustaqilTotal > 0 ? ' (Mustaqil ta\'lim: ' + mustaqilTotal + ' soat avtomatik)' : ''))
                    .removeClass('ktr-msg-success').addClass('ktr-msg-error').show();
            } else if (grandTotal > 0 && grandTotal === ktrState.totalLoad && allMatch) {
                $msg.html('Barcha soatlar to\'g\'ri taqsimlangan!' + (mustaqilTotal > 0 ? ' (Mustaqil ta\'lim: ' + mustaqilTotal + ' soat)' : ''))
                    .removeClass('ktr-msg-error').addClass('ktr-msg-success').show();
            } else {
                $msg.hide();
            }
        }

        function saveKtrPlan() {
            var codes = ktrState.filteredCodes;
            var hours = {};
            var topics = {};

            // Ketma-ket tartib raqamini hisoblash
            var seqCounters = {};
            codes.forEach(function(code) { seqCounters[code] = 0; });

            for (var w = 1; w <= ktrState.weekCount; w++) {
                hours[w] = {};
                topics[w] = {};
                codes.forEach(function(code) {
                    var val = parseInt($('.ktr-hours[data-week="' + w + '"][data-code="' + code + '"]').val()) || 0;
                    hours[w][code] = val;
                    if (val > 0) {
                        var topicText = getHemisTopic(code, seqCounters[code]);
                        if (topicText) topics[w][code] = topicText;
                        seqCounters[code]++;
                    }
                });
            }

            $('#ktr-save-btn').prop('disabled', true).text('Saqlanmoqda...');

            $.ajax({
                url: '/admin/ktr/plan/' + ktrState.csId,
                type: 'POST',
                data: JSON.stringify({
                    week_count: ktrState.weekCount,
                    plan_data: { hours: hours, topics: topics }
                }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(resp) {
                    $('#ktr-save-btn').prop('disabled', false).text('Saqlash');
                    if (resp.success) {
                        $('#ktr-validation-msg')
                            .html(resp.message)
                            .removeClass('ktr-msg-error').addClass('ktr-msg-success').show();

                        if (resp.is_draft && resp.change_request) {
                            // Draft sifatida saqlandi - approval panelni ko'rsatish
                            ktrState.changeRequest = resp.change_request;
                            ktrState.editMode = false;
                            $('#ktr-save-btn').hide();
                            $('#ktr-edit-btn').hide();
                            $('#ktr-week-selector').hide();
                            $('.ktr-hours').prop('readonly', true).prop('disabled', true);
                            renderChangePanel();
                        } else {
                            // To'g'ridan-to'g'ri saqlandi (yangi reja yoki admin)
                            ktrState.hasPlan = true;
                            $('#ktr-word-export-btn').show();
                            setTimeout(function() { $('#ktr-modal-overlay').fadeOut(200); }, 1000);
                        }
                    }
                },
                error: function(xhr) {
                    $('#ktr-save-btn').prop('disabled', false).text('Saqlash');
                    var msg = 'Xatolik yuz berdi';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    $('#ktr-validation-msg')
                        .html(msg)
                        .removeClass('ktr-msg-success').addClass('ktr-msg-error').show();
                }
            });
        }

        function exportKtrPlan() {
            var types = ktrState.trainingTypes;
            var codes = ktrState.filteredCodes;
            var title = $('#ktr-modal-title').text();

            // CSV content
            var rows = [];
            // BOM
            var bom = '\uFEFF';

            // Header row 1: Fan nomi
            rows.push([title]);
            rows.push([]);

            // Header row 2: column names - Hafta | Mavzu | Soatlar
            var header = ['Hafta', 'Mavzu'];
            codes.forEach(function(code) {
                header.push(types[code].name + ' (soat)');
            });
            rows.push(header);

            // Ketma-ket tartib raqamini hisoblash
            var seqCounters = {};
            codes.forEach(function(code) { seqCounters[code] = 0; });

            // Data rows
            for (var w = 1; w <= ktrState.weekCount; w++) {
                var row = [w + '-hafta'];
                var topicParts = [];
                codes.forEach(function(code) {
                    var hrs = $('.ktr-hours[data-week="' + w + '"][data-code="' + code + '"]').val() || '';
                    var val = parseInt(hrs) || 0;
                    if (val > 0) {
                        var topicName = getHemisTopic(code, seqCounters[code]);
                        if (topicName) topicParts.push(types[code].name + ': ' + topicName);
                        seqCounters[code]++;
                    }
                });
                row.push(topicParts.join('; '));
                codes.forEach(function(code) {
                    var hrs = $('.ktr-hours[data-week="' + w + '"][data-code="' + code + '"]').val() || '';
                    row.push(hrs);
                });
                rows.push(row);
            }

            // Jami row
            var jamiRow = ['Jami', ''];
            codes.forEach(function(code) {
                var colSum = 0;
                $('.ktr-hours[data-code="' + code + '"]').each(function() {
                    colSum += parseInt($(this).val()) || 0;
                });
                jamiRow.push(colSum);
            });
            rows.push(jamiRow);

            // Yuklama row
            var loadRow = ['Yuklama', ''];
            codes.forEach(function(code) {
                loadRow.push(types[code].hours);
            });
            rows.push(loadRow);

            // Build CSV
            var csv = bom + rows.map(function(row) {
                return row.map(function(cell) {
                    var s = String(cell == null ? '' : cell);
                    if (s.indexOf(';') > -1 || s.indexOf('"') > -1 || s.indexOf('\n') > -1) {
                        return '"' + s.replace(/"/g, '""') + '"';
                    }
                    return s;
                }).join(';');
            }).join('\n');

            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'KTR_' + title.replace(/[^a-zA-Z0-9\u0400-\u04FF]/g, '_') + '.csv';
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>

    <style>
        .ktr-search-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 20px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }
        .ktr-search-btn:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            transform: translateY(-1px);
        }
        .ktr-export-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.3);
            text-decoration: none;
        }
        .ktr-export-btn:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
            transform: translateY(-1px);
            color: #fff;
        }

        /* Filter container - Journal bilan bir xil */
        .filter-container {
            padding: 16px 20px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .filter-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
        }
        .filter-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
        }
        .fl-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        /* Toggle switch */
        .toggle-switch {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            padding: 6px 0;
        }
        .toggle-track {
            width: 36px;
            height: 20px;
            background: #cbd5e1;
            border-radius: 10px;
            position: relative;
            transition: background 0.2s;
            margin-right: 8px;
        }
        .toggle-switch.active .toggle-track {
            background: #3b82f6;
        }
        .toggle-thumb {
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-switch.active .toggle-thumb {
            transform: translateX(16px);
        }
        .toggle-label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }
        .toggle-switch.active .toggle-label {
            color: #3b82f6;
        }

        /* Journal table styles */
        .journal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .journal-table thead th {
            background: #f8fafc;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .journal-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .journal-row:hover {
            background: #eff6ff;
        }
        .th-num, .td-num {
            width: 40px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .badge-blue { background: #eff6ff; color: #1d4ed8; }
        .badge-violet { background: #f5f3ff; color: #7c3aed; }
        .badge-teal { background: #f0fdfa; color: #0f766e; }
        .badge-amber { background: #fffbeb; color: #b45309; }
        .badge-indigo { background: #eef2ff; color: #4338ca; }

        .text-cell { font-size: 13px; }
        .text-emerald { color: #059669; }
        .text-cyan { color: #0891b2; }
        .text-subject { color: #0f172a; font-weight: 500; }

        .sort-link {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .sort-link:hover { color: #3b82f6; }
        .sort-icon { font-size: 10px; color: #cbd5e1; }
        .sort-icon.active { color: #3b82f6; }

        /* Select2 customization */
        .select2-container--classic .select2-selection--single {
            border-radius: 8px !important;
            border-color: #e2e8f0 !important;
            height: 36px !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px !important;
            font-size: 13px !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 34px !important;
        }

        /* Training type column headers */
        .ktr-type-th {
            text-align: center !important;
            vertical-align: middle !important;
            min-width: 60px;
            max-width: 80px;
            white-space: normal !important;
            line-height: 1.3;
            padding: 8px 4px !important;
        }

        /* Modal overlay */
        .ktr-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .ktr-modal {
            background: #fff;
            border-radius: 16px;
            width: auto;
            min-width: 600px;
            max-width: 90vw;
            max-height: 90vh;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .ktr-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .ktr-modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .ktr-modal-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        .ktr-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
            padding: 0 4px;
            line-height: 1;
            transition: color 0.15s;
        }
        .ktr-modal-close:hover { color: #0f172a; }
        .ktr-modal-body {
            padding: 16px 20px;
            overflow-y: auto;
            flex: 1;
        }
        .ktr-modal-loading {
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            z-index: 10;
        }

        /* Week selector */
        .ktr-week-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .ktr-week-btn {
            width: 40px;
            height: 36px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s;
        }
        .ktr-week-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            background: #eff6ff;
        }
        .ktr-week-btn.active {
            border-color: #3b82f6;
            background: #3b82f6;
            color: #fff;
        }

        /* Plan table */
        .ktr-plan-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 13px;
        }
        .ktr-plan-table thead th {
            background: #f8fafc;
            padding: 6px 4px;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .ktr-th-hours {
            font-size: 10px;
            font-weight: 400;
            color: #94a3b8;
            margin-top: 2px;
        }
        .ktr-th-week { min-width: 55px; width: 55px; text-align: center; padding: 6px 4px !important; }
        .ktr-td-week {
            padding: 4px 6px;
            font-weight: 500;
            font-size: 12px;
            color: #475569;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
            text-align: center;
            width: 55px;
        }
        .ktr-td-hrs {
            padding: 3px;
            border: 1px solid #e2e8f0;
            text-align: center;
            width: 50px;
        }
        .ktr-hours {
            width: 100%;
            max-width: 44px;
            margin: 0 auto;
            display: block;
            padding: 4px 2px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
            outline: none;
            transition: border-color 0.15s;
            -moz-appearance: textfield;
        }
        .ktr-hours::-webkit-outer-spin-button,
        .ktr-hours::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .ktr-cell:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

        /* Topic cells */
        .ktr-td-topic {
            padding: 3px 6px;
            border: 1px solid #e2e8f0;
            min-width: 110px;
        }
        .ktr-topic-text {
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
            display: block;
        }
        .ktr-topic-hidden {
            visibility: hidden;
        }
        .ktr-th-sub {
            font-size: 10px !important;
            padding: 3px 4px !important;
            text-transform: none !important;
            color: #94a3b8 !important;
            background: #f1f5f9 !important;
            border: 1px solid #e2e8f0;
            font-weight: 500 !important;
        }
        .ktr-th-topic-combined {
            min-width: 200px;
            text-align: center;
            vertical-align: middle !important;
            background: #f0fdf4 !important;
            color: #166534 !important;
            font-weight: 700 !important;
        }
        .ktr-td-topic-empty {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .ktr-td-sum {
            padding: 8px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .ktr-td-load {
            padding: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            border: 1px solid #e2e8f0;
            background: #fffbeb;
        }

        /* Buttons */
        .ktr-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ktr-btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }
        .ktr-btn-primary:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-1px); }
        .ktr-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .ktr-btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .ktr-btn-secondary:hover { background: #e2e8f0; }
        .ktr-btn-edit {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
        }
        .ktr-btn-edit:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-1px);
        }
        .ktr-btn-export {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.3);
        }
        .ktr-btn-export:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            transform: translateY(-1px);
        }

        /* Validation message */
        .ktr-validation-msg {
            margin-top: 12px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }
        .ktr-msg-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .ktr-msg-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        /* O'zgartirish so'rovi paneli */
        .ktr-change-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 16px;
        }
        .ktr-change-title {
            font-size: 14px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 6px;
        }
        .ktr-change-desc {
            font-size: 13px;
            color: #78350f;
            margin-bottom: 10px;
        }
        .ktr-approver-list {
            list-style: none;
            padding: 0;
            margin: 0 0 12px;
        }
        .ktr-approver-list li {
            padding: 6px 0;
            border-bottom: 1px solid #fde68a;
            font-size: 13px;
            color: #451a03;
        }
        .ktr-approver-list li:last-child { border-bottom: none; }
        .ktr-approval-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 8px;
        }
        .ktr-approval-table th {
            background: #fef3c7;
            padding: 6px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            color: #92400e;
            border-bottom: 2px solid #fde68a;
        }
        .ktr-approval-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #fde68a;
            color: #451a03;
        }
        .ktr-approval-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        .ktr-info-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .ktr-info-item {
            display: inline-flex;
            align-items: center;
            font-size: 13px;
            color: #334155;
        }
        .ktr-info-item b {
            margin-right: 4px;
            color: #475569;
        }
        .ktr-approved { background: #dcfce7; color: #166534; }
        .ktr-rejected { background: #fef2f2; color: #dc2626; }
        .ktr-pending { background: #fef9c3; color: #854d0e; }
    </style>
</x-app-layout>
