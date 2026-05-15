{{--
    Qayta o'qish uchun cascading filtr paneli (talaba ma'lumotlari asosida).
    Mavjud admin.students.filter.* endpoint'larini qayta ishlatadi.

    Talab qilinadi:
        $formAction — formani action URL'i
        $educationTypes — Student modelidan ta'lim turlari kolleksiyasi

    Ixtiyoriy:
        $extraQueryFields — boshqa filter fieldlar uchun ['key' => 'value'] (hidden inputs)
        $hiddenFilters — render qilinmasin: ['education_type', 'group', ...]
--}}

@once
    {{-- jQuery + Select2 — teacher layoutida yuklanmagan, filtr panel ularga muhtoj. --}}
    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @endpush
@endonce

@php
    $hiddenFilters = $hiddenFilters ?? [];
    $extraQueryFields = $extraQueryFields ?? [];
    $hidden = fn ($key) => in_array($key, $hiddenFilters, true);
    $subjectMultiple = $subjectMultiple ?? false;
    $selectedSubjects = is_array(request('subject'))
        ? request('subject')
        : (filled(request('subject')) ? [request('subject')] : []);
@endphp

<form method="GET" action="{{ $formAction }}" id="retake-filter-form">
    @foreach($extraQueryFields as $k => $v)
        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
    @endforeach

    <div class="rf-container">
        <div class="rf-row">
            @if(!$hidden('education_type'))
                <div class="rf-item" style="min-width: 160px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                    <select id="rf_education_type" name="education_type" class="rf-select2" style="width: 100%;">
                        <option value="">Barchasi</option>
                        @foreach($educationTypes ?? [] as $type)
                            <option value="{{ $type->education_type_code }}" {{ request('education_type') == $type->education_type_code ? 'selected' : '' }}>
                                {{ $type->education_type_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(!$hidden('department'))
                <div class="rf-item" style="flex: 1; min-width: 200px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#10b981;"></span> Fakultet</label>
                    <select id="rf_department" name="department" class="rf-select2" style="width: 100%;">
                        <option value="">Barchasi</option>
                    </select>
                </div>
            @endif

            @if(!$hidden('specialty'))
                <div class="rf-item" style="flex: 1; min-width: 220px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                    <select id="rf_specialty" name="specialty" class="rf-select2" style="width: 100%;">
                        <option value="">Barchasi</option>
                    </select>
                </div>
            @endif

            @if(!$hidden('per_page'))
                <div class="rf-item" style="min-width: 90px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#94a3b8;"></span> Sahifada</label>
                    <select id="rf_per_page" name="per_page" class="rf-select2" style="width: 100%;">
                        @foreach([10, 25, 50, 100] as $ps)
                            <option value="{{ $ps }}" {{ request('per_page', 50) == $ps ? 'selected' : '' }}>{{ $ps }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div class="rf-row">
            @if(!$hidden('level_code'))
                <div class="rf-item" style="min-width: 130px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#8b5cf6;"></span> Kurs</label>
                    <select id="rf_level_code" name="level_code" class="rf-select2" style="width: 100%;">
                        <option value="">Barchasi</option>
                    </select>
                </div>
            @endif

            @if(!$hidden('semester_code'))
                <div class="rf-item" style="min-width: 140px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#14b8a6;"></span> Semestr</label>
                    <select id="rf_semester_code" name="semester_code" class="rf-select2" style="width: 100%;">
                        <option value="">Barchasi</option>
                    </select>
                </div>
            @endif

            @if(!$hidden('group'))
                <div class="rf-item" style="min-width: 160px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#1a3268;"></span> Guruh</label>
                    <select id="rf_group" name="group" class="rf-select2" style="width: 100%;">
                        <option value="">Barchasi</option>
                    </select>
                </div>
            @endif

            @if(!$hidden('subject') && !empty($subjects ?? []))
                <div class="rf-item" style="flex: 1; min-width: 200px;">
                    <label class="rf-label">
                        <span class="rf-dot" style="background:#000000;"></span> Fan
                        @if($subjectMultiple)
                            <span class="text-[10px] font-normal text-gray-500 normal-case">(bir nechta tanlash mumkin)</span>
                        @endif
                    </label>
                    @if($subjectMultiple)
                        <select id="rf_subject" name="subject[]" class="rf-select2" multiple data-placeholder="Barchasi" style="width: 100%;">
                            @foreach($subjects as $subjId => $subjName)
                                <option value="{{ $subjId }}" {{ in_array((string)$subjId, array_map('strval', $selectedSubjects), true) ? 'selected' : '' }}>{{ $subjName }}</option>
                            @endforeach
                        </select>
                    @else
                        <select id="rf_subject" name="subject" class="rf-select2" style="width: 100%;">
                            <option value="">Barchasi</option>
                            @foreach($subjects as $subjId => $subjName)
                                <option value="{{ $subjId }}" {{ (string)request('subject') === (string)$subjId ? 'selected' : '' }}>{{ $subjName }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endif

            @if(!$hidden('full_name'))
                <div class="rf-item" style="flex: 1; min-width: 200px;">
                    <label class="rf-label"><span class="rf-dot" style="background:#f59e0b;"></span> F.I.Sh / HEMIS ID</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Talaba F.I.Sh yoki HEMIS ID" class="rf-input">
                </div>
            @endif

            <div class="rf-item" style="min-width: 120px;">
                <label class="rf-label">&nbsp;</label>
                <button type="submit" class="rf-btn">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Qidirish
                </button>
            </div>

            <div class="rf-item" style="min-width: 90px;">
                <label class="rf-label">&nbsp;</label>
                <a href="{{ $formAction }}" class="rf-btn rf-btn-clear">Tozalash</a>
            </div>

            @isset($extraButton)
                <div class="rf-item" style="min-width: 120px;">
                    <label class="rf-label">&nbsp;</label>
                    {!! $extraButton !!}
                </div>
            @endisset
        </div>

        @isset($extraRow)
            <div class="rf-row">
                {!! $extraRow !!}
            </div>
        @endisset
    </div>
</form>

@push('scripts')
<script>
(function() {
    if (typeof $ === 'undefined') return;

    var initDone = false;
    var sv = {
        education_type: @json(request('education_type', '')),
        department: @json(request('department', '')),
        specialty: @json(request('specialty', '')),
        level_code: @json(request('level_code', '')),
        semester_code: @json(request('semester_code', '')),
        group: @json(request('group', ''))
    };

    function fp() {
        return {
            education_type: $('#rf_education_type').val() || '',
            department: $('#rf_department').val() || '',
            specialty: $('#rf_specialty').val() || '',
            level_code: $('#rf_level_code').val() || '',
            semester_code: $('#rf_semester_code').val() || ''
        };
    }

    function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }

    function pd(url, params, el, selVal, cb) {
        if ($(el).length === 0) { if (cb) cb(); return; }
        $.get(url, params, function(d) {
            $.each(d, function(k, v) {
                $(el).append('<option value="' + k + '">' + v + '</option>');
            });
            if (selVal) $(el).val(selVal);
            $(el).trigger('change');
            if (cb) cb();
        });
    }

    function rDept() { rd('#rf_department'); pd('{{ route("admin.students.filter.departments") }}', fp(), '#rf_department'); }
    function rSpec() { rd('#rf_specialty'); pd('{{ route("admin.students.filter.specialties") }}', fp(), '#rf_specialty'); }
    function rLvl() { rd('#rf_level_code'); pd('{{ route("admin.students.filter.levels") }}', fp(), '#rf_level_code'); }
    function rSem() { rd('#rf_semester_code'); pd('{{ route("admin.students.filter.semesters") }}', fp(), '#rf_semester_code'); }
    function rGrp() { rd('#rf_group'); pd('{{ route("admin.students.filter.groups") }}', fp(), '#rf_group'); }

    $(document).ready(function() {
        $('.rf-select2').each(function() {
            var $el = $(this);
            var isMulti = $el.prop('multiple');
            var placeholder = isMulti
                ? ($el.attr('data-placeholder') || 'Barchasi')
                : $el.find('option:first').text();
            $el.select2({
                theme: 'classic',
                width: '100%',
                allowClear: !isMulti,
                placeholder: placeholder,
                closeOnSelect: !isMulti
            });
        });

        $('#rf_education_type').on('change', function() { if (!initDone) return; rDept(); rSpec(); rLvl(); rSem(); rGrp(); });
        $('#rf_department').on('change', function() { if (!initDone) return; rSpec(); rGrp(); });
        $('#rf_specialty').on('change', function() { if (!initDone) return; rGrp(); });
        $('#rf_level_code').on('change', function() { if (!initDone) return; rSem(); rGrp(); });
        $('#rf_semester_code').on('change', function() { if (!initDone) return; rGrp(); });

        var loaded = 0;
        function checkInit() { loaded++; if (loaded >= 5) initDone = true; }

        pd('{{ route("admin.students.filter.departments") }}',
            {education_type: sv.education_type},
            '#rf_department', sv.department, checkInit);

        pd('{{ route("admin.students.filter.specialties") }}',
            {education_type: sv.education_type, department: sv.department},
            '#rf_specialty', sv.specialty, checkInit);

        pd('{{ route("admin.students.filter.levels") }}',
            {education_type: sv.education_type, department: sv.department, specialty: sv.specialty},
            '#rf_level_code', sv.level_code, checkInit);

        pd('{{ route("admin.students.filter.semesters") }}',
            {education_type: sv.education_type, level_code: sv.level_code},
            '#rf_semester_code', sv.semester_code, checkInit);

        pd('{{ route("admin.students.filter.groups") }}',
            {education_type: sv.education_type, department: sv.department, specialty: sv.specialty, level_code: sv.level_code, semester_code: sv.semester_code},
            '#rf_group', sv.group, checkInit);
    });
})();
</script>
@endpush

@once
@push('styles')
<style>
.rf-container { padding: 14px 16px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border: 1px solid #dbe4ef; border-radius: 12px; margin-bottom: 16px; }
.rf-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
.rf-row:last-child { margin-bottom: 0; }
.rf-item { display: flex; flex-direction: column; }
.rf-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
.rf-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.rf-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; color: #1e293b; box-sizing: border-box; }
.rf-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.2); }
.rf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 36px; padding: 0 14px; background: #2b5ea7; color: #fff; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; }
.rf-btn:hover { background: #1e4a87; }
.rf-btn-clear { background: #e5e7eb; color: #1f2937; }
.rf-btn-clear:hover { background: #d1d5db; }
</style>
@endpush
@endonce
