<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost topshirilish holati
        </h2>
    </x-slot>

    @php
        $statusBadge = [
            'pending'   => ['Kutilmoqda', '#475569', '#f1f5f9'],
            'received'  => ['Qabul qilindi', '#1d4ed8', '#dbeafe'],
            'reviewing' => ['Tekshirilmoqda', '#b45309', '#fef3c7'],
            'approved'  => ['Tasdiqlandi', '#166534', '#dcfce7'],
            'rejected'  => ['Rad etildi', '#b91c1c', '#fee2e2'],
        ];
        $closingFormLabels = [
            'oski' => 'Faqat OSKI', 'test' => 'Faqat Test', 'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ', 'sinov' => 'Sinov (test)',
        ];

        $curSort = request('sort');
        $curDir = request('dir') === 'desc' ? 'desc' : 'asc';
        $sortUrl = function ($col) use ($curSort, $curDir) {
            $dir = ($curSort === $col && $curDir === 'asc') ? 'desc' : 'asc';
            return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $dir, 'page' => 1]);
        };
        $arrow = function ($col) use ($curSort, $curDir) {
            if ($curSort !== $col) return '<span style="color:#cbd5e1;">⇅</span>';
            return $curDir === 'asc' ? '▲' : '▼';
        };
    @endphp

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            @if(session('success'))
                <div style="background:#dcfce7;color:#166534;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #bbf7d0;">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div style="background:#fee2e2;color:#b91c1c;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #fecaca;">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Statistika --}}
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                @foreach($statusBadge as $key => $b)
                    <div style="flex:1;min-width:130px;background:{{ $b[2] }};border-radius:10px;padding:12px 16px;">
                        <div style="font-size:12px;color:{{ $b[1] }};font-weight:600;">{{ $b[0] }}</div>
                        <div style="font-size:22px;font-weight:700;color:{{ $b[1] }};">{{ $stats[$key] ?? 0 }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="overflow:visible;">
                @if($canToggleNotify)
                    {{-- Vedomost xabarlari toggle (faqat admin) — faqat shu bo'limga tegishli xabarlarni yoqadi/o'chiradi --}}
                    <div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;padding:10px 16px 0;">
                        <span style="font-size:12px;color:#64748b;">Vedomost xabarlari (Telegram + tizim):</span>
                        <form method="POST" action="{{ route('admin.vedomost-submission.toggle-notify') }}">
                            @csrf
                            @foreach(request()->query() as $k => $val)
                                @if(!is_array($val))<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endif
                            @endforeach
                            <input type="hidden" name="enabled" value="{{ $notifyEnabled ? 0 : 1 }}">
                            <button type="submit" title="Bosib yoqish/o'chirish"
                                style="display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;background:{{ $notifyEnabled ? '#dcfce7' : '#fee2e2' }};color:{{ $notifyEnabled ? '#166534' : '#b91c1c' }};padding:6px 14px;border-radius:999px;font-weight:700;font-size:13px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:{{ $notifyEnabled ? '#16a34a' : '#dc2626' }};display:inline-block;"></span>
                                {{ $notifyEnabled ? 'Yoqilgan' : "O'chirilgan" }}
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Filtrlar (yopilish shakli sahifasi uslubida) --}}
                <form id="filter-form" method="GET" action="{{ route('admin.vedomost-submission.index') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="min-width:160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                                <select name="education_type" id="education_type" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? request('education_type')) == $type->education_type_code ? 'selected' : '' }}>
                                            {{ $type->education_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex:1;min-width:200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                                <select name="faculty" id="faculty" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>{{ $faculty->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex:1;min-width:220px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                                <select name="specialty" id="specialty" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($specialties as $sp)
                                        <option value="{{ $sp }}" {{ request('specialty') === $sp ? 'selected' : '' }}>{{ $sp }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:120px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select name="level_code" id="level_code" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                        </div>

                        <div class="filter-row">
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                                <select name="semester_code" id="semester_code" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="flex:1;min-width:180px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ea580c;"></span> Fan</label>
                                <input type="text" name="subject_name" id="subject_name" value="{{ request('subject_name') }}" placeholder="Fan nomini kiriting..." class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width:160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#059669;"></span> Yopilish shakli</label>
                                <select name="closing_form_filter" id="closing_form_filter" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($closingForms as $k => $label)
                                        <option value="{{ $k }}" {{ request('closing_form_filter') === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:150px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#64748b;"></span> Status</label>
                                <select name="status" id="status" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach(\App\Models\VedomostSubmission::statusLabels() as $k => $label)
                                        <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:90px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                                <select name="per_page" class="select2" style="width:100%;">
                                    @foreach([25, 50, 100, 200] as $pageSize)
                                        <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>{{ $pageSize }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:150px;">
                                <label class="filter-label">&nbsp;</label>
                                <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#334155;height:36px;">
                                    <input type="checkbox" name="overdue" value="1" {{ request('overdue') ? 'checked' : '' }}>
                                    Faqat kechikkanlar
                                </label>
                            </div>
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-search">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Generatsiya + Excel --}}
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;flex-wrap:wrap;gap:8px;">
                    <span style="font-size:13px;color:#64748b;">Jami: {{ $submissions->total() }} ta</span>
                    <div style="display:flex;gap:8px;">
                        <a href="{{ route('admin.vedomost-submission.export', request()->query()) }}"
                           style="background:#1d6f42;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            Excel
                        </a>
                        <form method="POST" action="{{ route('admin.vedomost-submission.sync', request()->query()) }}"
                              onsubmit="return confirm('Joriy semestr bo\'yicha vedomost yozuvlari yangilansinmi?');">
                            @csrf
                            <button type="submit" style="background:#1a3268;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;">
                                ↻ Joriy semestr bo'yicha yangilash
                            </button>
                        </form>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="vd-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><a href="{{ $sortUrl('group') }}">Guruh {!! $arrow('group') !!}</a></th>
                                <th>Yo'nalish</th>
                                <th><a href="{{ $sortUrl('subject') }}">Fan {!! $arrow('subject') !!}</a></th>
                                <th><a href="{{ $sortUrl('department') }}">Kafedra {!! $arrow('department') !!}</a></th>
                                <th><a href="{{ $sortUrl('teacher') }}">O'qituvchi {!! $arrow('teacher') !!}</a></th>
                                <th>Fan mas'uli</th>
                                <th>Kafedra mudiri</th>
                                <th><a href="{{ $sortUrl('closing_form') }}">Yopilish {!! $arrow('closing_form') !!}</a></th>
                                <th><a href="{{ $sortUrl('base_date') }}">Asos sana {!! $arrow('base_date') !!}</a></th>
                                <th><a href="{{ $sortUrl('deadline') }}">Muddat {!! $arrow('deadline') !!}</a></th>
                                <th><a href="{{ $sortUrl('status') }}">Status {!! $arrow('status') !!}</a></th>
                                <th>Amal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $i => $v)
                                @php $overdue = $v->deadline && \Carbon\Carbon::parse($v->deadline)->isPast() && !\Carbon\Carbon::parse($v->deadline)->isToday() && $v->status !== 'approved'; @endphp
                                <tr>
                                    <td style="color:#94a3b8;">{{ $submissions->firstItem() + $i }}</td>
                                    <td style="font-weight:600;">
                                        {{ $v->group_name }}
                                        @if(($v->merge_count ?? 1) > 1)
                                            <span class="vd-merge" title="Guruhchalar: {{ $v->subgroup_label }}">{{ $v->merge_count }} guruhcha</span>
                                        @endif
                                    </td>
                                    <td style="color:#64748b;">{{ $v->specialty_name }}</td>
                                    <td>{{ $v->subject_name }}</td>
                                    <td style="color:#64748b;">{{ $v->department_name }}</td>
                                    <td class="vd-person" title="Tel: {{ $v->teacher_phone ?: '—' }}">{{ $v->teacher_name ?? '—' }}</td>
                                    <td class="vd-person" title="Tel: {{ $v->fan_masuli_phone ?: '—' }}">{{ $v->fan_masuli_name ?? '—' }}</td>
                                    <td class="vd-person" title="Tel: {{ $v->kafedra_mudiri_phone ?: '—' }}">{{ $v->kafedra_mudiri_name ?? '—' }}</td>
                                    <td>{{ $closingFormLabels[$v->closing_form] ?? $v->closing_form }}</td>
                                    <td>
                                        {{ $v->base_date ? \Carbon\Carbon::parse($v->base_date)->format('d.m.Y') : '—' }}
                                        <div style="font-size:10px;color:#94a3b8;">{{ $v->base_type === 'lesson' ? 'oxirgi dars' : ($v->base_type === 'exam' ? 'YN sanasi' : '') }}</div>
                                    </td>
                                    <td>
                                        @if($v->deadline)
                                            <span style="{{ $overdue ? 'color:#b91c1c;font-weight:700;' : 'color:#334155;' }}">{{ \Carbon\Carbon::parse($v->deadline)->format('d.m.Y') }}</span>
                                            @if($overdue)<div style="font-size:10px;color:#b91c1c;">kechikkan</div>@endif
                                        @else — @endif
                                    </td>
                                    <td>
                                        @php $b = $statusBadge[$v->status] ?? ['—','#475569','#f1f5f9']; @endphp
                                        <span style="background:{{ $b[2] }};color:{{ $b[1] }};padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap;">{{ $b[0] }}</span>
                                        @if(!empty($v->is_mixed_status))
                                            <div style="font-size:10px;color:#b45309;margin-top:2px;" title="Guruhchalar statusi har xil">aralash holat</div>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.vedomost-submission.show', $v->id) }}"
                                           style="color:#1a3268;font-weight:600;text-decoration:none;white-space:nowrap;">Batafsil →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="13" style="padding:40px;text-align:center;color:#94a3b8;">Ma'lumot yo'q. "Joriy semestr bo'yicha yangilash" tugmasini bosing.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:14px 16px;">{{ $submissions->links() }}</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.select2').each(function () {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text() });
            });

            const selLevel = @json(request('level_code'));
            const selSem = @json(request('semester_code'));

            function reset(el, ph) { $(el).empty().append('<option value="">' + ph + '</option>'); }
            function populate(url, params, el, cb) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    $.each(data, function (k, v) { $(el).append('<option value="' + k + '">' + v + '</option>'); });
                    if (cb) cb();
                }});
            }
            function loadLevels(preselect) {
                reset('#level_code', 'Barchasi');
                populate('{{ route('admin.closing-form.get-level-codes') }}', {}, '#level_code', function () {
                    if (preselect) $('#level_code').val(preselect);
                    $('#level_code').trigger('change.select2');
                });
            }
            function loadSemesters(preselect) {
                reset('#semester_code', 'Barchasi');
                populate('{{ route('admin.closing-form.get-semesters') }}', { level_code: $('#level_code').val() }, '#semester_code', function () {
                    if (preselect) $('#semester_code').val(preselect);
                    $('#semester_code').trigger('change.select2');
                });
            }
            loadLevels(selLevel);
            loadSemesters(selSem);
            $('#level_code').on('change', function () { loadSemesters(); });
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; border-radius: 12px 12px 0 0; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-item { display: flex; flex-direction: column; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .filter-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 0.8rem; color: #1e293b; background: #fff; width: 100%; outline: none; }
        .filter-input:hover { border-color: #2b5ea7; }
        .btn-search { height: 36px; display: inline-flex; align-items: center; gap: 6px; background: #1a3268; color: #fff; border: none; padding: 0 18px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; }
        .btn-search:hover { background: #15264f; }

        .vd-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .vd-table thead th { background: #f1f5f9; text-align: left; padding: 9px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #475569; white-space: nowrap; }
        .vd-table thead th a { color: #475569; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .vd-table thead th a:hover { color: #1a3268; }
        .vd-table tbody td { padding: 8px 10px; border-top: 1px solid #f1f5f9; vertical-align: top; }
        .vd-table tbody tr:hover { background: #f8fafc; }
        .vd-person { cursor: help; }
        .vd-merge { display:inline-block; margin-left:6px; background:#eef2ff; color:#4338ca; font-size:10px; font-weight:700; padding:1px 7px; border-radius:999px; cursor:help; white-space:nowrap; }
    </style>
</x-app-layout>
