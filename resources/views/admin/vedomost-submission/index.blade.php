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
        // Shakl badge: [matn rang, fon rang]
        $formBadge = [
            '12'   => ['#1a3268', '#e0e7ff'],
            '12q'  => ['#3730a3', '#eef2ff'],
            '12a'  => ['#9a3412', '#ffedd5'],
            '12aq' => ['#b45309', '#fef3c7'],
            '12ag' => ['#92400e', '#fde68a'],
            '12b'  => ['#9d174d', '#fce7f3'],
            '12bq' => ['#a21caf', '#fae8ff'],
            '12bg' => ['#7c2d12', '#fed7aa'],
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
            <div id="vedomost-sync-box" style="display:none;background:#e0f2fe;color:#075985;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #bae6fd;"></div>

            {{-- Statistika — kartochkalar ham filtr: bosilganda shu status bo'yicha saralanadi --}}
            @php
                $activeStatus = request('status');
                $overdueOn = request()->boolean('overdue');
                $statUrl = fn (array $q) => route('admin.vedomost-submission.index', array_merge(\Illuminate\Support\Arr::except(request()->query(), ['status', 'overdue', 'page']), $q));
            @endphp
            <div class="vd-stats">
                <a href="{{ $statUrl([]) }}" class="vd-stat {{ !$activeStatus && !$overdueOn ? 'is-active' : '' }}" style="--c:#1a3268;--bg:#eef2ff;">
                    <span class="vd-stat-label">Jami</span>
                    <span class="vd-stat-num">{{ $stats['total'] ?? 0 }}</span>
                </a>
                @foreach($statusBadge as $key => $b)
                    <a href="{{ $statUrl($activeStatus === $key ? [] : ['status' => $key]) }}" class="vd-stat {{ $activeStatus === $key ? 'is-active' : '' }}" style="--c:{{ $b[1] }};--bg:{{ $b[2] }};">
                        <span class="vd-stat-label">{{ $b[0] }}</span>
                        <span class="vd-stat-num">{{ $stats[$key] ?? 0 }}</span>
                        @if(($stats['total'] ?? 0) > 0)
                            <span class="vd-stat-bar"><span style="width:{{ round(($stats[$key] ?? 0) * 100 / $stats['total']) }}%"></span></span>
                        @endif
                    </a>
                @endforeach
                <a href="{{ $statUrl($overdueOn ? [] : ['overdue' => 1]) }}" class="vd-stat {{ $overdueOn ? 'is-active' : '' }}" style="--c:#b91c1c;--bg:#fff1f2;">
                    <span class="vd-stat-label">Muddati o'tgan</span>
                    <span class="vd-stat-num">{{ $stats['overdue'] ?? 0 }}</span>
                </a>
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
                            <div class="filter-item vs-switch-item" style="min-width:170px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#16a34a;"></span> Davr</label>
                                {{-- Standart yoqilgan: o'chirilganda yashirin "0" yuboriladi --}}
                                <input type="hidden" name="current_semester" value="0">
                                <label class="vs-switch" title="Faqat guruhlar hozir o'qiyotgan semestr vedomostlari">
                                    <input type="checkbox" name="current_semester" value="1" id="current_semester"
                                           {{ request('current_semester', '1') === '1' ? 'checked' : '' }}
                                           onchange="this.form.submit()">
                                    <span class="vs-switch-track" aria-hidden="true"><span class="vs-switch-thumb"></span></span>
                                    Joriy semestr
                                </label>
                                @if(!empty($currentSemesterHint))
                                    <span class="vs-switch-hint" title="Har guruh o'zi hozir o'qiyotgan semestr bo'yicha (talabalar ma'lumotidan)">{{ $currentSemesterHint }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="filter-row">
                            <div class="filter-item" style="min-width:120px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select name="level_code" id="level_code" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
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
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#6366f1;"></span> Shakl</label>
                                <select name="form_type" id="form_type" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($formLabels as $k => $label)
                                        <option value="{{ $k }}" {{ request('form_type') === $k ? 'selected' : '' }}>{{ $label }}</option>
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

                {{-- Faol filtrlar + amallar --}}
                @php
                    $filterLabels = [
                        'faculty' => fn ($v) => 'Fakultet: ' . ($faculties->firstWhere('id', $v)->name ?? $v),
                        'specialty' => fn ($v) => "Yo'nalish: " . $v,
                        'level_code' => fn ($v) => 'Kurs: ' . (is_numeric($v) ? ((int) $v - 10) . '-kurs' : $v),
                        'semester_code' => fn ($v) => 'Semestr: ' . (is_numeric($v) ? ((int) $v - 10) . '-semestr' : $v),
                        'subject_name' => fn ($v) => 'Fan: ' . $v,
                        'closing_form_filter' => fn ($v) => 'Yopilish: ' . ($closingForms[$v] ?? $v),
                        'form_type' => fn ($v) => 'Shakl: ' . ($formLabels[$v] ?? $v),
                        'status' => fn ($v) => 'Status: ' . (\App\Models\VedomostSubmission::statusLabels()[$v] ?? $v),
                        'overdue' => fn ($v) => "Muddati o'tgan",
                    ];
                    $activeFilters = [];
                    foreach ($filterLabels as $k => $label) {
                        if (request()->filled($k) && !($k === 'overdue' && !request()->boolean('overdue'))) {
                            $activeFilters[$k] = $label(request($k));
                        }
                    }
                    $currentOn = request('current_semester', '1') === '1';
                @endphp
                <div class="vd-toolbar">
                    <div class="vd-toolbar-left">
                        <span class="vd-total"><b>{{ $submissions->total() }}</b> ta vedomost</span>
                        <span class="vd-chip {{ $currentOn ? 'is-green' : 'is-gray' }}">{{ $currentOn ? 'Joriy semestr' : 'Barcha semestrlar' }}</span>
                        @foreach($activeFilters as $k => $label)
                            <a class="vd-chip" href="{{ route('admin.vedomost-submission.index', \Illuminate\Support\Arr::except(request()->query(), [$k, 'page'])) }}" title="Filtrni olib tashlash">{{ $label }} <span aria-hidden="true">✕</span></a>
                        @endforeach
                        @if(!empty($activeFilters))
                            <a class="vd-reset" href="{{ route('admin.vedomost-submission.index', array_filter(['education_type' => request('education_type'), 'current_semester' => request('current_semester'), 'per_page' => request('per_page')])) }}">Filtrlarni tozalash</a>
                        @endif
                    </div>
                    <div class="vd-toolbar-right">
                        <div class="vd-group" role="group" aria-label="Yuklab olish">
                            <a class="vd-btn" href="{{ route('admin.vedomost-submission.report', request()->query()) }}" title="Svodnaya hisobot">
                                <svg viewBox="0 0 24 24"><path d="M9 17v-6h13M9 17H4V5a2 2 0 012-2h7l5 5v9h-5M9 17v4h10v-4"/></svg>Hisobot
                            </a>
                            <a class="vd-btn" href="{{ route('admin.vedomost-submission.export', request()->query()) }}" title="Jadvalni Excelga yuklash">
                                <svg viewBox="0 0 24 24"><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>Excel
                            </a>
                        </div>
                        <div class="vd-group is-teal" role="group" aria-label="Tasdiqlanganlar jamlanmasi">
                            <span class="vd-group-label">Jamlanma</span>
                            <a class="vd-btn" href="{{ route('admin.vedomost-submission.jamlanma', request()->query()) }}"
                               title="Joriy filtrdagi barcha TASDIQLANGAN vedomostlar — bitta jadvalda («Vedomost tekshirish» formati, A–AJ + yopilish shakli)">Filtrdagilar</a>
                            <button type="submit" form="jam-form" id="jam-selected" class="vd-btn" disabled
                                    title="Jadvalda belgilangan tasdiqlangan vedomostlar jamlanmasi">Tanlanganlar <span class="vd-badge" id="jam-count">0</span></button>
                        </div>
                        @if(!empty($canManage))
                            <a class="vd-btn is-ghost" href="{{ route('admin.vedomost-submission.ai-settings') }}">🤖 AI sozlamalari</a>
                            <button type="button" id="manual-open-trigger" class="vd-btn is-ghost">+ Pullik uchun shakl</button>
                        @endif
                        <form method="POST" action="{{ route('admin.vedomost-submission.sync', request()->query()) }}"
                              onsubmit="return confirm('Joriy semestr bo\'yicha vedomost yozuvlari yangilansinmi?');">
                            @csrf
                            <button id="vedomost-sync-btn" type="submit" class="vd-btn is-primary" title="Joriy semestr bo'yicha vedomost yozuvlarini yaratish/yangilash">↻ Yangilash</button>
                        </form>
                    </div>
                </div>

                @if(!empty($canManage))
                    <div id="manual-open-modal"
                         style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(6px);z-index:9999;padding:24px;align-items:center;justify-content:center;">
                        <div style="width:min(720px, 100%);background:#f1f5f9;border-radius:16px;box-shadow:0 20px 50px rgba(15,23,42,0.25);overflow:hidden;">
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid #cbd5e1;">
                                <div>
                                    <div style="font-size:20px;font-weight:700;color:#0f172a;">Pullik uchun shakl ochish</div>
                                    <div style="font-size:13px;color:#475569;margin-top:4px;">Guruh, fan va shaklni tanlab qo'lda oching.</div>
                                </div>
                                <button type="button" id="manual-open-close"
                                        style="border:none;background:transparent;color:#334155;font-size:28px;cursor:pointer;line-height:1;">×</button>
                            </div>
                            <form method="POST" action="{{ route('admin.vedomost-submission.manual-open', request()->query()) }}" style="padding:20px;">
                                @csrf
                                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:14px;">
                                    <div>
                                        <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Guruh</label>
                                        <select id="manual-open-group" name="group_hemis_id" required
                                                style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;background:#fff;color:#0f172a;">
                                            <option value="">Guruhni tanlang</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Fan</label>
                                        <select id="manual-open-subject" name="subject_id" required
                                                style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;background:#fff;color:#0f172a;">
                                            <option value="">Avval guruhni tanlang</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Shakl</label>
                                        <select id="manual-open-form-type" name="form_type" required
                                                style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;background:#fff;color:#0f172a;">
                                            <option value="{{ \App\Models\VedomostSubmission::FORM_12A }}">12a-shakl</option>
                                            <option value="{{ \App\Models\VedomostSubmission::FORM_12B }}">12b-shakl</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Cheklov</label>
                                        <label style="display:flex;align-items:center;gap:8px;height:42px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#0f172a;">
                                            <input type="checkbox" name="only_paid" value="1" checked>
                                            Faqat pullik talabalar uchun
                                        </label>
                                    </div>
                                </div>
                                <input type="hidden" id="manual-open-semester" name="semester_code" value="">
                                <div id="manual-open-meta" style="margin-top:14px;font-size:12px;color:#64748b;"></div>
                                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">
                                    <button type="button" id="manual-open-cancel"
                                            style="background:#e2e8f0;color:#334155;border:none;padding:10px 16px;border-radius:10px;cursor:pointer;">
                                        Bekor qilish
                                    </button>
                                    <button type="submit"
                                            style="background:#2563eb;color:#fff;border:none;padding:10px 18px;border-radius:10px;cursor:pointer;font-weight:600;">
                                        Shaklni ochish
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <form id="jam-form" method="GET" action="{{ route('admin.vedomost-submission.jamlanma') }}"></form>
                <div class="vd-table-wrap">
                    <table class="vd-table">
                        <thead>
                            <tr>
                                <th style="width:28px;"><input type="checkbox" id="jam-all" title="Sahifadagi barcha tasdiqlanganlarni belgilash"></th>
                                <th>#</th>
                                <th><a href="{{ $sortUrl('group') }}">Guruh {!! $arrow('group') !!}</a></th>
                                <th>Shakl</th>
                                <th>Fakultet</th>
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
                                <tr class="{{ $overdue ? 'is-overdue' : '' }} {{ $v->status === 'approved' ? 'is-approved' : '' }}">
                                    <td>
                                        @if($v->status === 'approved' || !empty($v->is_mixed_status))
                                            <input type="checkbox" class="jam-cb" name="ids[]" form="jam-form" value="{{ implode(',', $v->ids ?? [$v->id]) }}" title="Jamlanmaga qo'shish (faqat tasdiqlangan guruhchalar olinadi)">
                                        @endif
                                    </td>
                                    <td style="color:#94a3b8;">{{ $submissions->firstItem() + $i }}</td>
                                    <td style="font-weight:600;">
                                        {{ $v->group_name }}
                                        @if(($v->merge_count ?? 1) > 1 && ($v->form_type ?? '12') === '12')
                                            <span class="vd-merge" title="Guruhchalar: {{ $v->subgroup_label }}">{{ $v->merge_count }} guruhcha</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $fb = $formBadge[$v->form_type ?? '12'] ?? ['#475569','#f1f5f9']; @endphp
                                        <span style="background:{{ $fb[1] }};color:{{ $fb[0] }};padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;">{{ \App\Models\VedomostSubmission::formLabel($v->form_type ?? '12') }}</span>
                                    </td>
                                    <td style="color:#64748b;">{{ $v->faculty_name ?? '—' }}</td>
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
                                <tr><td colspan="16" class="vd-empty">
                                    <div class="vd-empty-title">Vedomost topilmadi</div>
                                    @if(!empty($activeFilters))
                                        Tanlangan filtrlarga mos yozuv yo'q — filtrlarni o'zgartiring yoki tozalang.
                                    @elseif($currentOn)
                                        Joriy semestr uchun yozuvlar hali yaratilmagan bo'lishi mumkin — «↻ Yangilash» tugmasini bosing.
                                        O'tgan semestrlarni ko'rish uchun «Joriy semestr»ni o'chiring.
                                    @else
                                        «↻ Yangilash» tugmasini bosing.
                                    @endif
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:14px 16px;">{{ $submissions->links() }}</div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const all = document.getElementById('jam-all');
            const btn = document.getElementById('jam-selected');
            const cbs = () => [...document.querySelectorAll('.jam-cb')];
            const sync = () => {
                const n = cbs().filter(c => c.checked).length;
                document.getElementById('jam-count').textContent = n;
                btn.disabled = n === 0;
                if (all) all.checked = n > 0 && n === cbs().length;
            };
            all?.addEventListener('change', () => { cbs().forEach(c => c.checked = all.checked); sync(); });
            document.addEventListener('change', e => { if (e.target.classList?.contains('jam-cb')) sync(); });
        })();
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            const progressUrl = @json(route('admin.vedomost-submission.sync.progress'));
            const initialProgress = @json($syncProgress ?? ['status' => 'idle']);
            const manualOpenGroups = @json($manualOpenGroups ?? []);
            const syncBox = document.getElementById('vedomost-sync-box');
            const syncBtn = document.getElementById('vedomost-sync-btn');
            const manualOpenModal = document.getElementById('manual-open-modal');
            const manualOpenTrigger = document.getElementById('manual-open-trigger');
            const manualOpenClose = document.getElementById('manual-open-close');
            const manualOpenCancel = document.getElementById('manual-open-cancel');
            const manualOpenGroup = document.getElementById('manual-open-group');
            const manualOpenSubject = document.getElementById('manual-open-subject');
            const manualOpenSemester = document.getElementById('manual-open-semester');
            const manualOpenMeta = document.getElementById('manual-open-meta');
            const manualOpenFormType = document.getElementById('manual-open-form-type');
            let syncPollTimer = null;
            let lastStatus = initialProgress && initialProgress.status ? initialProgress.status : 'idle';

            function renderSyncState(state, isInitial) {
                const status = state && state.status ? state.status : 'idle';
                const message = state && state.message ? state.message : '';

                if (status === 'queued' || status === 'running') {
                    syncBox.style.display = 'block';
                    syncBox.style.background = '#e0f2fe';
                    syncBox.style.color = '#075985';
                    syncBox.style.borderColor = '#bae6fd';
                    syncBox.textContent = message || "Joriy semester bo'yicha yangilash ishlamoqda...";
                    if (syncBtn) {
                        syncBtn.disabled = true;
                        syncBtn.style.opacity = '0.7';
                        syncBtn.style.cursor = 'not-allowed';
                    }
                } else if (status === 'done') {
                    syncBox.style.display = 'block';
                    syncBox.style.background = '#dcfce7';
                    syncBox.style.color = '#166534';
                    syncBox.style.borderColor = '#bbf7d0';
                    syncBox.textContent = message || "Yangilash tugadi.";
                    if (syncBtn) {
                        syncBtn.disabled = false;
                        syncBtn.style.opacity = '1';
                        syncBtn.style.cursor = 'pointer';
                    }
                    if (!isInitial && (lastStatus === 'queued' || lastStatus === 'running')) {
                        window.setTimeout(function () { window.location.reload(); }, 1200);
                    }
                } else if (status === 'error') {
                    syncBox.style.display = 'block';
                    syncBox.style.background = '#fee2e2';
                    syncBox.style.color = '#b91c1c';
                    syncBox.style.borderColor = '#fecaca';
                    syncBox.textContent = message || "Yangilashda xatolik yuz berdi.";
                    if (syncBtn) {
                        syncBtn.disabled = false;
                        syncBtn.style.opacity = '1';
                        syncBtn.style.cursor = 'pointer';
                    }
                } else {
                    syncBox.style.display = 'none';
                    syncBox.textContent = '';
                    if (syncBtn) {
                        syncBtn.disabled = false;
                        syncBtn.style.opacity = '1';
                        syncBtn.style.cursor = 'pointer';
                    }
                }

                const shouldPoll = status === 'queued' || status === 'running';
                lastStatus = status;
                return shouldPoll;
            }

            function pollSync() {
                if (syncPollTimer) {
                    window.clearTimeout(syncPollTimer);
                }

                $.ajax({
                    url: progressUrl,
                    type: 'GET',
                    success: function (data) {
                        if (renderSyncState(data, false)) {
                            syncPollTimer = window.setTimeout(pollSync, 3000);
                        }
                    },
                    error: function () {
                        syncPollTimer = window.setTimeout(pollSync, 5000);
                    }
                });
            }

            if (renderSyncState(initialProgress, true)) {
                pollSync();
            }

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

            function fillManualGroups() {
                if (!manualOpenGroup) {
                    return;
                }

                manualOpenGroup.innerHTML = '<option value="">Guruhni tanlang</option>';
                manualOpenGroups.forEach(function (group) {
                    const option = document.createElement('option');
                    option.value = group.group_hemis_id;
                    option.textContent = group.group_name + (group.specialty_name ? ' — ' + group.specialty_name : '');
                    manualOpenGroup.appendChild(option);
                });

                if ($(manualOpenGroup).hasClass('select2-hidden-accessible')) {
                    $(manualOpenGroup).select2('destroy');
                }
                $(manualOpenGroup).select2({
                    dropdownParent: $('#manual-open-modal'),
                    theme: 'classic',
                    width: '100%',
                    placeholder: 'Guruhni typing qilib qidiring'
                });
            }

            function fillManualSubjects(groupId) {
                if (!manualOpenSubject) {
                    return;
                }

                manualOpenSubject.innerHTML = '<option value="">' + (groupId ? 'Fanni tanlang' : 'Avval guruhni tanlang') + '</option>';
                manualOpenSemester.value = '';
                manualOpenMeta.textContent = '';

                const selectedGroup = manualOpenGroups.find(function (group) {
                    return group.group_hemis_id === groupId;
                });

                if (!selectedGroup) {
                    return;
                }

                selectedGroup.subjects.forEach(function (subject) {
                    const option = document.createElement('option');
                    option.value = subject.subject_id;
                    option.textContent = subject.subject_name + ' (' + (subject.semester_name || (subject.semester_code + '-semestr')) + ')';
                    option.dataset.semesterCode = subject.semester_code;
                    option.dataset.semesterName = subject.semester_name || '';
                    option.dataset.closingForm = subject.closing_form;
                    manualOpenSubject.appendChild(option);
                });
            }

            function syncManualMeta() {
                if (!manualOpenSubject) {
                    return;
                }

                const selected = manualOpenSubject.options[manualOpenSubject.selectedIndex];
                const semesterCode = selected ? (selected.dataset.semesterCode || '') : '';
                const semesterName = selected ? (selected.dataset.semesterName || '') : '';
                const closingForm = selected ? (selected.dataset.closingForm || '') : '';

                manualOpenSemester.value = semesterCode;
                manualOpenMeta.textContent = semesterCode
                    ? 'Semestr: ' + (semesterName || semesterCode) + (closingForm ? ' | Yopilish shakli: ' + closingForm : '')
                    : '';
            }

            function resetManualModal() {
                if (!manualOpenGroup || !manualOpenSubject) {
                    return;
                }

                $(manualOpenGroup).val('').trigger('change');
                manualOpenSubject.innerHTML = '<option value="">Avval guruhni tanlang</option>';
                manualOpenSubject.value = '';
                if (manualOpenFormType) {
                    manualOpenFormType.value = '{{ \App\Models\VedomostSubmission::FORM_12A }}';
                }
                manualOpenSemester.value = '';
                manualOpenMeta.textContent = '';
            }

            function openManualModal() {
                if (!manualOpenModal) {
                    return;
                }

                resetManualModal();
                manualOpenModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }

            function closeManualModal() {
                if (!manualOpenModal) {
                    return;
                }

                manualOpenModal.style.display = 'none';
                document.body.style.overflow = '';
            }

            fillManualGroups();

            if (manualOpenTrigger) {
                manualOpenTrigger.addEventListener('click', openManualModal);
            }
            if (manualOpenClose) {
                manualOpenClose.addEventListener('click', closeManualModal);
            }
            if (manualOpenCancel) {
                manualOpenCancel.addEventListener('click', closeManualModal);
            }
            if (manualOpenModal) {
                manualOpenModal.addEventListener('click', function (event) {
                    if (event.target === manualOpenModal) {
                        closeManualModal();
                    }
                });
            }
            if (manualOpenGroup) {
                $(manualOpenGroup).on('change select2:select', function () {
                    fillManualSubjects($(this).val());
                });
            }
            if (manualOpenSubject) {
                $(manualOpenSubject).on('change', syncManualMeta);
            }
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; border-radius: 12px 12px 0 0; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; align-items: flex-end; }
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

        #manual-open-modal .select2-container--classic .select2-selection--single {
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            box-shadow: none;
        }
        #manual-open-modal .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
            padding-left: 12px;
            padding-right: 34px;
            color: #0f172a;
            font-size: 14px;
        }
        #manual-open-modal .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 8px;
        }
        #manual-open-modal .select2-container {
            width: 100% !important;
        }
        /* Statistika kartochkalari */
        .vd-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .vd-stat { position: relative; display: flex; flex-direction: column; gap: 2px; background: var(--bg); border: 2px solid transparent; border-radius: 12px; padding: 11px 14px 13px; text-decoration: none; color: var(--c); transition: transform .12s, box-shadow .12s, border-color .12s; }
        .vd-stat:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,23,42,.08); }
        .vd-stat.is-active { border-color: var(--c); box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 15%, transparent); }
        .vd-stat-label { font-size: 12px; font-weight: 600; }
        .vd-stat-num { font-size: 24px; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
        .vd-stat-bar { position: absolute; left: 14px; right: 14px; bottom: 6px; height: 3px; border-radius: 3px; background: color-mix(in srgb, var(--c) 15%, transparent); overflow: hidden; }
        .vd-stat-bar span { display: block; height: 100%; background: var(--c); border-radius: 3px; }

        /* Amallar paneli */
        .vd-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 10px 16px; flex-wrap: wrap; padding: 12px 16px; border-bottom: 1px solid #eef2f7; }
        .vd-toolbar-left, .vd-toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .vd-total { font-size: 13px; color: #475569; margin-right: 4px; }
        .vd-total b { color: #0f172a; font-size: 15px; }
        .vd-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: #f1f5f9; color: #334155; font-size: 12px; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0; }
        a.vd-chip:hover { background: #fee2e2; border-color: #fecaca; color: #b91c1c; }
        .vd-chip.is-green { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .vd-chip.is-gray { background: #f8fafc; color: #64748b; }
        .vd-reset { font-size: 12px; color: #2563eb; font-weight: 600; text-decoration: none; }
        .vd-reset:hover { text-decoration: underline; }
        .vd-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 13px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #1e293b; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; transition: background .12s, border-color .12s; }
        .vd-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .vd-btn:disabled { opacity: .5; cursor: not-allowed; }
        .vd-btn svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .vd-btn.is-primary { background: #1a3268; border-color: #1a3268; color: #fff; }
        .vd-btn.is-primary:hover { background: #15264f; }
        .vd-btn.is-ghost { border-color: transparent; background: #f8fafc; }
        .vd-btn.is-ghost:hover { background: #eef2ff; border-color: #c7d2fe; }
        .vd-group { display: inline-flex; align-items: center; border: 1px solid #cbd5e1; border-radius: 9px; overflow: hidden; background: #fff; }
        .vd-group .vd-btn { border: none; border-radius: 0; height: 32px; }
        .vd-group .vd-btn + .vd-btn { border-left: 1px solid #e2e8f0; }
        .vd-group.is-teal { border-color: #99f6e4; }
        .vd-group.is-teal .vd-btn { color: #0f766e; }
        .vd-group.is-teal .vd-btn:hover:not(:disabled) { background: #f0fdfa; }
        .vd-group-label { padding: 0 10px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #fff; background: #0f766e; align-self: stretch; display: flex; align-items: center; }
        .vd-badge { min-width: 18px; padding: 0 6px; border-radius: 999px; background: #0f766e; color: #fff; font-size: 11px; line-height: 18px; text-align: center; }

        /* Jadval */
        .vd-table-wrap { overflow: auto; max-height: calc(100vh - 140px); }
        .vd-table thead th { position: sticky; top: 0; z-index: 2; box-shadow: inset 0 -1px 0 #e2e8f0; }
        .vd-table tbody tr.is-overdue td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
        .vd-table tbody tr.is-approved { background: #fbfefc; }
        .vd-empty { padding: 48px 20px !important; text-align: center; color: #64748b; font-size: 13px; line-height: 1.7; }
        .vd-empty-title { font-size: 15px; font-weight: 700; color: #334155; margin-bottom: 4px; }

            /* Joriy semestr toggle */
        .vs-switch { display: flex; align-items: center; gap: 8px; height: 36px; font-size: 13px; font-weight: 600; color: #334155; cursor: pointer; user-select: none; }
        .vs-switch input { position: absolute; opacity: 0; width: 1px; height: 1px; }
        .vs-switch-track { position: relative; flex: none; width: 38px; height: 22px; border-radius: 999px; background: #cbd5e1; transition: background .18s; }
        .vs-switch-thumb { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.25); transition: transform .18s; }
        .vs-switch input:checked + .vs-switch-track { background: #16a34a; }
        .vs-switch input:checked + .vs-switch-track .vs-switch-thumb { transform: translateX(16px); }
        .vs-switch input:focus-visible + .vs-switch-track { outline: 2px solid #2563eb; outline-offset: 2px; }
        .vs-switch-item { position: relative; }
        .vs-switch-hint { position: absolute; left: 0; top: 100%; font-size: 10.5px; color: #64748b; white-space: nowrap; }
    </style>
</x-app-layout>
