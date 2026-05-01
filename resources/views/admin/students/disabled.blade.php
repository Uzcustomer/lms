<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Nogiron talabalar') }}
        </h2>
    </x-slot>

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Xato!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Muvaffaqiyatli!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <form id="search-form" method="GET" action="{{ route('admin.students.disabled') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Nogironlik turi</label>
                                <select name="disability_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($disabilityTypes as $type)
                                        <option value="{{ $type->disability_type_code }}" {{ request('disability_type') == $type->disability_type_code ? 'selected' : '' }}>
                                            {{ $type->disability_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                                <select name="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->department_id }}" {{ request('department') == $d->department_id ? 'selected' : '' }}>
                                            {{ $d->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 170px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <select name="group" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($groups as $g)
                                        <option value="{{ $g->group_id }}" {{ request('group') == $g->group_id ? 'selected' : '' }}>
                                            {{ $g->group_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select name="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($levels as $l)
                                        <option value="{{ $l->level_code }}" {{ request('level_code') == $l->level_code ? 'selected' : '' }}>
                                            {{ $l->level_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                                <select name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $ps)
                                        <option value="{{ $ps }}" {{ request('per_page', 50) == $ps ? 'selected' : '' }}>{{ $ps }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-item" style="flex: 1; min-width: 220px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.Sh</label>
                                <input type="text" name="full_name" value="{{ request('full_name') }}"
                                       placeholder="Obidov Zohid" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ec4899;"></span> Talaba ID</label>
                                <input type="text" name="student_id_number" value="{{ request('student_id_number') }}"
                                       placeholder="1234" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width: 120px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                            <div class="filter-item" style="min-width: 120px;">
                                <label class="filter-label">&nbsp;</label>
                                <a href="{{ route('admin.students.disabled') }}" class="btn-reset">
                                    Tozalash
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span class="badge" style="background:linear-gradient(135deg,#b91c1c,#ef4444);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ $students->total() }} ta nogiron talaba</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                        <tr>
                            <th style="width:50px;text-align:center;">#</th>
                            <th>F.I.Sh</th>
                            <th>Talaba ID</th>
                            <th>Fakultet</th>
                            <th>Yo'nalish</th>
                            <th>Kurs</th>
                            <th>Guruh</th>
                            <th>Nogironlik turi</th>
                            <th>Muddati</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($students as $i => $student)
                            <tr>
                                <td style="text-align:center;color:#64748b;">{{ $students->firstItem() + $i }}</td>
                                <td>
                                    <a href="{{ route('admin.students.show', $student->id) }}" class="student-name-link">
                                        {{ $student->full_name }}
                                    </a>
                                </td>
                                <td style="color:#64748b;">{{ $student->student_id_number }}</td>
                                <td><span class="text-cell text-emerald">{{ $student->department_name }}</span></td>
                                <td><span class="text-cell text-cyan" title="{{ $student->specialty_name }}">{{ Str::limit($student->specialty_name, 30) }}</span></td>
                                <td><span class="badge badge-violet">{{ $student->level_name }}</span></td>
                                <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                <td><span class="badge badge-red">{{ $student->disability_type_name ?: '-' }}</span></td>
                                <td><span class="text-cell">{{ $student->disability_duration ?: '-' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align:center;padding:40px 20px;color:#94a3b8;font-size:13px;">
                                    Nogiron talabalar topilmadi.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                    <div class="flex-1 flex justify-between sm:hidden">
                        {{ $students->appends(request()->query())->links('pagination::simple-tailwind') }}
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 leading-5">
                                {!! __('Showing') !!}
                                <span class="font-medium">{{ $students->firstItem() ?? 0 }}</span>
                                {!! __('to') !!}
                                <span class="font-medium">{{ $students->lastItem() ?? 0 }}</span>
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
        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text()
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

        .btn-reset { display: inline-flex; align-items: center; justify-content: center; padding: 8px 20px; background: #fff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; height: 36px; text-decoration: none; }
        .btn-reset:hover { background: #f1f5f9; border-color: #94a3b8; }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .student-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .student-table thead { position: sticky; top: 0; z-index: 10; }
        .student-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .student-table th { padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .student-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .student-table tbody tr:nth-child(even) { background: #f8fafc; }
        .student-table tbody tr:nth-child(odd) { background: #fff; }
        .student-table tbody tr:hover { background: #fef2f2 !important; box-shadow: inset 4px 0 0 #ef4444; }
        .student-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }

        .student-name-link { color: #1e40af; font-weight: 700; text-decoration: none; transition: all 0.15s; }
        .student-name-link:hover { color: #2b5ea7; text-decoration: underline; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
        .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; white-space: nowrap; }
    </style>
</x-app-layout>
