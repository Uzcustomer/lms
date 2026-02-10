<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Xodimlar') }}
        </h2>
    </x-slot>

    @if (session('error'))
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.teachers.index') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 200px; flex: 1;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#3b82f6;"></span> Qidirish
                                </label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Ism, ID..."
                                       class="filter-input">
                            </div>

                            <div class="filter-item" style="min-width: 220px; flex: 2;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#f59e0b;"></span> Kafedra
                                </label>
                                <select name="department" id="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#8b5cf6;"></span> Lavozim
                                </label>
                                <select name="staff_position" id="staff_position" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($positions as $pos)
                                        <option value="{{ $pos }}" {{ request('staff_position') == $pos ? 'selected' : '' }}>{{ $pos }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 150px;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#06b6d4;"></span> Rol
                                </label>
                                <select name="role" id="role" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($activeRoles as $roleName)
                                        <option value="{{ $roleName }}" {{ request('role') == $roleName ? 'selected' : '' }}>
                                            {{ \App\Enums\ProjectRole::tryFrom($roleName)?->label() ?? $roleName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 110px;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#10b981;"></span> Status
                                </label>
                                <select name="status" id="status" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Faol</option>
                                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Nofaol</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 110px;">
                                <label class="filter-label">
                                    <span class="fl-dot" style="background:#ef4444;"></span> Holati
                                </label>
                                <select name="is_active" id="is_active" class="select2" style="width: 100%;">
                                    <option value="1" {{ request('is_active', '1') === '1' ? 'selected' : '' }}>Aktiv</option>
                                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Noaktiv</option>
                                    <option value="" {{ request()->has('is_active') && request('is_active') === '' ? 'selected' : '' }}>Barchasi</option>
                                </select>
                            </div>

                            <div style="display: flex; align-items: flex-end; gap: 6px; padding-bottom: 2px;">
                                <a href="{{ route('admin.teachers.export-excel', request()->query()) }}" class="filter-export-btn" title="Excelga yuklash">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Excel
                                </a>
                                @if(request()->hasAny(['search', 'department', 'staff_position', 'role', 'status', 'is_active']))
                                    <a href="{{ route('admin.teachers.index') }}" class="filter-clear-btn" title="Filtrlarni tozalash">
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Tozalash
                                    </a>
                                @endif
                                <div id="filter-loading" style="display: none; align-items: center; color: #2b5ea7;">
                                    <svg class="animate-spin" style="height: 16px; width: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="overflow-x-auto">
                    @if($teachers->isEmpty())
                        <div style="padding: 60px 20px; text-align: center;">
                            <svg style="width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <p style="color: #94a3b8; font-size: 14px;">Xodimlar topilmadi.</p>
                        </div>
                    @else
                        <table class="teachers-table">
                            <thead>
                            <tr>
                                <th style="width: 44px; padding-left: 16px;">#</th>
                                <th>Xodim</th>
                                <th>Kafedra</th>
                                <th>Lavozim</th>
                                <th>Rollar</th>
                                <th style="width: 80px;">Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($teachers as $index => $teacher)
                                <tr onclick="window.location='{{ route('admin.teachers.show', $teacher) }}'" style="cursor: pointer;">
                                    <td style="padding-left: 16px; font-weight: 700; color: #2b5ea7; font-size: 13px;">
                                        {{ $teachers->firstItem() + $index }}
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="flex-shrink: 0;">
                                                @if($teacher->image)
                                                    <img style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" src="{{ $teacher->image }}" alt="">
                                                @else
                                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #e0e7ff; display: flex; align-items: center; justify-content: center;">
                                                        <span style="color: #4338ca; font-weight: 600; font-size: 11px;">{{ mb_substr($teacher->first_name ?? '', 0, 1) }}{{ mb_substr($teacher->second_name ?? '', 0, 1) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div style="min-width: 0;">
                                                <div style="font-size: 12.5px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $teacher->full_name }}</div>
                                                <div style="font-size: 11px; color: #94a3b8;">{{ $teacher->employee_id_number }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-cell" style="color: #92400e; max-width: 220px; white-space: normal; word-break: break-word;">{{ $teacher->department ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="text-cell" style="color: #5b21b6;">{{ $teacher->staff_position ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 3px;">
                                            @forelse($teacher->roles as $role)
                                                <span class="badge badge-indigo">
                                                    {{ \App\Enums\ProjectRole::tryFrom($role->name)?->label() ?? $role->name }}
                                                </span>
                                            @empty
                                                <span style="color: #cbd5e1; font-size: 12px;">-</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        @if($teacher->status)
                                            <span class="badge badge-green">Faol</span>
                                        @else
                                            <span class="badge badge-red">Nofaol</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                    {{ $teachers->links() }}
                </div>
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

        $(document).ready(function () {
            let isInitialLoad = true;
            let autoSubmitTimeout = null;

            function autoSubmitForm() {
                if (isInitialLoad) return;
                clearTimeout(autoSubmitTimeout);
                autoSubmitTimeout = setTimeout(function() {
                    $('#filter-loading').css('display', 'flex');
                    $('#filter-form').submit();
                }, 400);
            }

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

            // Autosubmit on select change
            $('#department, #staff_position, #role, #status, #is_active').on('change', function() {
                autoSubmitForm();
            });

            // Autosubmit on search input with debounce
            let searchTimeout = null;
            $('#search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    autoSubmitForm();
                }, 600);
            });

            setTimeout(function() { isInitialLoad = false; }, 800);
        });
    </script>

    <style>
        /* ===== Filter Container ===== */
        .filter-container {
            padding: 16px 20px 12px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf5 100%);
            border-bottom: 2px solid #dbe4ef;
        }
        .filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-item { }

        /* ===== Filter Labels ===== */
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

        /* ===== Filter Input ===== */
        .filter-input {
            width: 100%;
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            padding: 0 10px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #1e293b;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            outline: none;
        }
        .filter-input:hover {
            border-color: #2b5ea7;
            box-shadow: 0 0 0 2px rgba(43,94,167,0.1);
        }
        .filter-input:focus {
            border-color: #2b5ea7;
            box-shadow: 0 0 0 3px rgba(43,94,167,0.15);
        }
        .filter-input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        /* ===== Export Button ===== */
        .filter-export-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 600;
            color: #166534;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
            height: 36px;
        }
        .filter-export-btn:hover {
            background: #bbf7d0;
            border-color: #86efac;
            color: #14532d;
        }

        /* ===== Clear Button ===== */
        .filter-clear-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 600;
            color: #dc2626;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
            height: 36px;
        }
        .filter-clear-btn:hover {
            background: #fee2e2;
            border-color: #f87171;
            color: #b91c1c;
        }

        /* ===== Select2 ===== */
        .select2-container--classic .select2-selection--single {
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .select2-container--classic .select2-selection--single:hover {
            border-color: #2b5ea7;
            box-shadow: 0 0 0 2px rgba(43,94,167,0.1);
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px;
            padding-left: 10px;
            padding-right: 52px;
            color: #1e293b;
            font-size: 0.8rem;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 34px;
            width: 22px;
            background: transparent;
            border-left: none;
            right: 0;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 22px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            font-weight: bold;
            color: #94a3b8;
            cursor: pointer;
            padding: 2px 6px;
            z-index: 2;
            background: #ffffff;
            border-radius: 50%;
            line-height: 1;
            transition: all 0.15s;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover {
            color: #ffffff;
            background: #ef4444;
        }
        .select2-dropdown {
            font-size: 0.8rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .select2-container--classic .select2-results__option--highlighted {
            background-color: #2b5ea7;
        }

        /* ===== Table ===== */
        .teachers-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .teachers-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .teachers-table thead tr {
            background: linear-gradient(135deg, #e8edf5 0%, #dbe4ef 50%, #d1d9e6 100%);
        }
        .teachers-table th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11.5px;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: 2px solid #cbd5e1;
        }

        /* ===== Table Body ===== */
        .teachers-table tbody tr {
            transition: all 0.15s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .teachers-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .teachers-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .teachers-table tbody tr:hover {
            background-color: #eff6ff !important;
            box-shadow: inset 4px 0 0 #2b5ea7;
        }
        .teachers-table td {
            padding: 10px 12px;
            vertical-align: middle;
            line-height: 1.4;
        }

        /* ===== Text Cells ===== */
        .text-cell {
            font-size: 12.5px;
            font-weight: 500;
            line-height: 1.35;
            display: block;
        }

        /* ===== Badges ===== */
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1.4;
        }
        .badge-indigo {
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            color: #ffffff;
            border: none;
            white-space: nowrap;
        }
        .badge-green { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* ===== Action Button ===== */
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #2b5ea7;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .action-btn:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }

        /* ===== Spin Animation ===== */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
    </style>
</x-app-layout>
