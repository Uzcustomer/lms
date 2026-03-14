<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ Auth::guard('teacher')->user()->short_name }} ga tegishli Talabalar ro'yxati
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <form id="search-form" method="GET" action="{{ route('teacher.students') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.Sh</label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}"
                                       placeholder="F.I.O yoki ID raqam..." class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width: 180px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <select id="group" name="group" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($tutorGroups as $group)
                                        <option value="{{ $group->group_hemis_id }}" {{ request('group') == $group->group_hemis_id ? 'selected' : '' }}>{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Jinsi</label>
                                <select id="gender" name="gender" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    <option value="11" {{ request('gender') == '11' ? 'selected' : '' }}>Erkak</option>
                                    <option value="12" {{ request('gender') == '12' ? 'selected' : '' }}>Ayol</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 180px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Viloyat</label>
                                <select id="province" name="province" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($provinces as $province)
                                        <option value="{{ $province }}" {{ request('province') == $province ? 'selected' : '' }}>{{ $province }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                                <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $ps)
                                        <option value="{{ $ps }}" {{ request('per_page', 50) == $ps ? 'selected' : '' }}>{{ $ps }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 120px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span class="badge" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ $students->total() }} ta talaba</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>F.I.Sh</th>
                            <th>ID raqam</th>
                            <th>Guruh</th>
                            <th style="text-align:center;">Jinsi</th>
                            <th>Viloyat</th>
                            <th style="text-align:center;">GPA</th>
                            <th>Holat</th>
                            <th>Telefon</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($students as $index => $student)
                            <tr>
                                <td style="color:#94a3b8;font-size:12px;">{{ $students->firstItem() + $index }}</td>
                                <td>
                                    <a href="{{ route('teacher.students.show', $student) }}" class="student-name-link" style="display:flex;align-items:center;gap:10px;">
                                        @if($student->image)
                                            <img src="{{ $student->image }}" alt="{{ $student->full_name }}"
                                                 style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;flex-shrink:0;">
                                        @else
                                            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#cbd5e1,#94a3b8);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;font-weight:700;color:#fff;">
                                                {{ mb_substr($student->full_name, 0, 1) }}
                                            </div>
                                        @endif
                                        <span>{{ $student->full_name }}</span>
                                    </a>
                                </td>
                                <td style="color:#64748b;font-family:monospace;font-size:12px;">{{ $student->student_id_number }}</td>
                                <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                <td style="text-align:center;">
                                    @if($student->gender_code == '11')
                                        <span class="badge" style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">Erkak</span>
                                    @elseif($student->gender_code == '12')
                                        <span class="badge" style="background:#fce7f3;color:#9d174d;border:1px solid #fbcfe8;">Ayol</span>
                                    @else
                                        <span style="color:#94a3b8;">-</span>
                                    @endif
                                </td>
                                <td><span class="text-cell" style="color:#475569;">{{ $student->province_name ?? '-' }}</span></td>
                                <td style="text-align:center;">
                                    @if($student->avg_gpa)
                                        <span class="badge" style="{{ $student->avg_gpa >= 3.5 ? 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;' : ($student->avg_gpa >= 2.5 ? 'background:#fef9c3;color:#854d0e;border:1px solid #fde68a;' : 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;') }}font-weight:700;">
                                            {{ number_format($student->avg_gpa, 2) }}
                                        </span>
                                    @else
                                        <span style="color:#94a3b8;">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($student->student_status_code == '11' || $student->student_status_name == 'Faol')
                                        <span class="badge" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">{{ $student->student_status_name ?? 'Faol' }}</span>
                                    @else
                                        <span class="badge" style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;">{{ $student->student_status_name ?? '-' }}</span>
                                    @endif
                                </td>
                                <td style="font-size:12px;color:#475569;">
                                    {{ $student->phone ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="padding: 40px; text-align: center; color: #94a3b8; font-size: 14px;">
                                    Talabalar topilmadi
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:between;">
                    <div class="flex-1 flex justify-between sm:hidden">
                        {{ $students->appends(request()->query())->links('pagination::simple-tailwind') }}
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 leading-5">
                                {!! __('Showing') !!}
                                <span class="font-medium">{{ $students->firstItem() }}</span>
                                {!! __('to') !!}
                                <span class="font-medium">{{ $students->lastItem() }}</span>
                                {!! __('of') !!}
                                <span class="font-medium">{{ $students->total() }}</span>
                                {!! __('results') !!}
                            </p>
                        </div>
                        <div>
                            {{ $students->appends(request()->query())->links('pagination::tailwind') }}
                        </div>
                    </div>
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

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() {
                        var s = document.querySelector('.select2-container--open .select2-search__field');
                        if (s) s.focus();
                    }, 10);
                });
            });
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .filter-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; box-sizing: border-box; }
        .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.2); }
        .filter-input::placeholder { color: #94a3b8; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .student-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .student-table thead { position: sticky; top: 0; z-index: 10; }
        .student-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .student-table th { padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .student-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .student-table tbody tr:nth-child(even) { background: #f8fafc; }
        .student-table tbody tr:nth-child(odd) { background: #fff; }
        .student-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .student-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }

        .student-name-link { color: #1e40af; font-weight: 700; text-decoration: none; transition: all 0.15s; }
        .student-name-link:hover { color: #2b5ea7; text-decoration: underline; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
    </style>
</x-teacher-app-layout>
