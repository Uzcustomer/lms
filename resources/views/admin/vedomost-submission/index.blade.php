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

                {{-- Generatsiya + Excel --}}
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;flex-wrap:wrap;gap:8px;">
                    <span style="font-size:13px;color:#64748b;">Jami: {{ $submissions->total() }} ta</span>
                    <div style="display:flex;gap:8px;">
                        @if(!empty($canManage))
                            <button type="button"
                                    id="manual-open-trigger"
                                    style="background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Mavjud bo'lmagan shaklni ochish
                            </button>
                        @endif
                        <a href="{{ route('admin.vedomost-submission.report', request()->query()) }}"
                           style="background:#1a3268;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h13M9 17H4V5a2 2 0 012-2h7l5 5v9h-5M9 17v4h10v-4"/></svg>
                            Hisobot
                        </a>
                        <a href="{{ route('admin.vedomost-submission.export', request()->query()) }}"
                           style="background:#1d6f42;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            Excel
                        </a>
                        <form method="POST" action="{{ route('admin.vedomost-submission.sync', request()->query()) }}"
                              onsubmit="return confirm('Joriy semestr bo\'yicha vedomost yozuvlari yangilansinmi?');">
                            @csrf
                            <button id="vedomost-sync-btn" type="submit" style="background:#1a3268;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;">
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
                                <tr>
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
                                <tr><td colspan="15" style="padding:40px;text-align:center;color:#94a3b8;">Ma'lumot yo'q. "Joriy semestr bo'yicha yangilash" tugmasini bosing.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:14px 16px;">{{ $submissions->links() }}</div>
            </div>
        </div>
    </div>

    @if(!empty($canManage))
        <div id="manual-open-modal"
             style="display:none;position:fixed;inset:0;z-index:80;background:rgba(71,85,105,0.38);backdrop-filter:blur(6px);padding:24px;align-items:center;justify-content:center;">
            <div style="width:min(720px,100%);background:#f8fafc;border-radius:18px;box-shadow:0 30px 80px rgba(15,23,42,0.28);border:1px solid rgba(148,163,184,0.3);overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #e2e8f0;">
                    <div>
                        <div style="font-size:22px;font-weight:700;color:#0f172a;">Mavjud bo'lmagan shaklni ochish</div>
                        <div style="font-size:13px;color:#64748b;margin-top:4px;">Guruhni tanlang, keyin shu guruh fanlaridan keraklisini va shakl turini belgilang.</div>
                    </div>
                    <button type="button"
                            id="manual-open-close"
                            style="border:none;background:transparent;color:#334155;font-size:28px;line-height:1;cursor:pointer;">×</button>
                </div>

                <form id="manual-open-form" method="POST" style="padding:22px;">
                    @csrf
                    <div id="manual-open-error"
                         style="display:none;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:10px;padding:10px 12px;margin-bottom:16px;"></div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Guruh</label>
                            <select id="manual-open-group"
                                    style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;background:#fff;color:#0f172a;">
                                <option value="">Guruhni tanlang</option>
                            </select>
                        </div>

                        <div>
                            <label style="display:block;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Fan</label>
                            <select id="manual-open-subject"
                                    style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;background:#fff;color:#0f172a;"
                                    disabled>
                                <option value="">Avval guruhni tanlang</option>
                            </select>
                        </div>

                        <div>
                            <label style="display:block;font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Shakl</label>
                            <select id="manual-open-form-type"
                                    name="form_type"
                                    style="width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;background:#fff;color:#0f172a;"
                                    disabled>
                                <option value="">Avval fanni tanlang</option>
                            </select>
                        </div>
                    </div>

                    <div id="manual-open-info"
                         style="margin-top:16px;padding:14px 16px;border-radius:12px;background:#e0f2fe;color:#0f172a;display:none;">
                        <div style="font-size:13px;color:#0369a1;font-weight:700;margin-bottom:4px;">Tanlangan ma'lumot</div>
                        <div id="manual-open-info-text" style="font-size:14px;color:#1e293b;"></div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:22px;">
                        <button type="button"
                                id="manual-open-cancel"
                                style="height:40px;padding:0 16px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;">
                            Bekor qilish
                        </button>
                        <button type="submit"
                                id="manual-open-submit"
                                style="height:40px;padding:0 18px;border-radius:10px;border:none;background:#2563eb;color:#fff;cursor:pointer;font-weight:700;"
                                disabled>
                            Shaklni ochish
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            const progressUrl = @json(route('admin.vedomost-submission.sync.progress'));
            const initialProgress = @json($syncProgress ?? ['status' => 'idle']);
            const manualOpenRouteTemplate = @json(route('admin.vedomost-submission.manual-open', ['id' => '__ID__']));
            const manualOpenOptions = @json($manualOpenOptions ?? ['groups' => [], 'subjects_by_group' => []]);
            const syncBox = document.getElementById('vedomost-sync-box');
            const syncBtn = document.getElementById('vedomost-sync-btn');
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

            const modal = document.getElementById('manual-open-modal');
            const modalTrigger = document.getElementById('manual-open-trigger');
            const modalClose = document.getElementById('manual-open-close');
            const modalCancel = document.getElementById('manual-open-cancel');
            const groupSelect = document.getElementById('manual-open-group');
            const subjectSelect = document.getElementById('manual-open-subject');
            const formTypeSelect = document.getElementById('manual-open-form-type');
            const submitBtn = document.getElementById('manual-open-submit');
            const form = document.getElementById('manual-open-form');
            const errorBox = document.getElementById('manual-open-error');
            const infoBox = document.getElementById('manual-open-info');
            const infoText = document.getElementById('manual-open-info-text');

            function toggleModal(show) {
                if (!modal) return;
                modal.style.display = show ? 'flex' : 'none';
            }

            function setError(message) {
                if (!errorBox) return;
                errorBox.textContent = message || '';
                errorBox.style.display = message ? 'block' : 'none';
            }

            function populateSelect(select, items, placeholder, mapper) {
                if (!select) return;
                select.innerHTML = '';
                const option = document.createElement('option');
                option.value = '';
                option.textContent = placeholder;
                select.appendChild(option);

                items.forEach(function (item) {
                    const normalized = mapper(item);
                    const opt = document.createElement('option');
                    opt.value = normalized.value;
                    opt.textContent = normalized.label;
                    if (normalized.dataset) {
                        Object.keys(normalized.dataset).forEach(function (key) {
                            opt.dataset[key] = normalized.dataset[key];
                        });
                    }
                    select.appendChild(opt);
                });

                select.disabled = items.length === 0;
            }

            function selectedSubjectObject() {
                const groupId = groupSelect ? groupSelect.value : '';
                const sourceId = subjectSelect ? subjectSelect.value : '';
                const subjects = (manualOpenOptions.subjects_by_group || {})[groupId] || [];
                return subjects.find(function (item) {
                    return String(item.source_id) === String(sourceId);
                }) || null;
            }

            function refreshInfo() {
                const subject = selectedSubjectObject();
                const formName = formTypeSelect && formTypeSelect.selectedOptions[0]
                    ? formTypeSelect.selectedOptions[0].textContent
                    : '';

                if (!subject || !formTypeSelect.value) {
                    infoBox.style.display = 'none';
                    submitBtn.disabled = true;
                    return;
                }

                const groupName = groupSelect.selectedOptions[0] ? groupSelect.selectedOptions[0].textContent : '';
                infoText.textContent = groupName + ' / ' + subject.name + ' / ' + formName + ' ochiladi.';
                infoBox.style.display = 'block';
                submitBtn.disabled = false;
            }

            function fillFormTypes(subject) {
                populateSelect(
                    formTypeSelect,
                    subject ? (subject.form_options || []) : [],
                    subject ? 'Shaklni tanlang' : 'Avval fanni tanlang',
                    function (item) {
                        return { value: item.id, label: item.name };
                    }
                );
                formTypeSelect.value = '';
                refreshInfo();
            }

            function fillSubjects(groupId) {
                const subjects = (manualOpenOptions.subjects_by_group || {})[groupId] || [];
                populateSelect(
                    subjectSelect,
                    subjects,
                    subjects.length ? 'Fanni tanlang' : 'Bu guruh uchun fan topilmadi',
                    function (item) {
                        return {
                            value: item.source_id,
                            label: item.name + ' (' + item.semester_code + '-semestr)',
                        };
                    }
                );
                subjectSelect.value = '';
                fillFormTypes(null);
            }

            function resetManualModal() {
                setError('');
                infoBox.style.display = 'none';
                submitBtn.disabled = true;
                populateSelect(
                    groupSelect,
                    manualOpenOptions.groups || [],
                    'Guruhni tanlang',
                    function (item) {
                        return { value: item.id, label: item.name };
                    }
                );
                groupSelect.value = '';
                populateSelect(subjectSelect, [], 'Avval guruhni tanlang', function () { return {}; });
                populateSelect(formTypeSelect, [], 'Avval fanni tanlang', function () { return {}; });
                subjectSelect.disabled = true;
                formTypeSelect.disabled = true;
                form.setAttribute('action', '');
            }

            if (modalTrigger) {
                modalTrigger.addEventListener('click', function () {
                    resetManualModal();
                    toggleModal(true);
                });
            }

            [modalClose, modalCancel].forEach(function (btn) {
                if (!btn) return;
                btn.addEventListener('click', function () {
                    toggleModal(false);
                });
            });

            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        toggleModal(false);
                    }
                });
            }

            if (groupSelect) {
                groupSelect.addEventListener('change', function () {
                    setError('');
                    fillSubjects(groupSelect.value);
                });
            }

            if (subjectSelect) {
                subjectSelect.addEventListener('change', function () {
                    setError('');
                    const subject = selectedSubjectObject();
                    if (subject) {
                        form.setAttribute('action', manualOpenRouteTemplate.replace('__ID__', String(subject.source_id)));
                    } else {
                        form.setAttribute('action', '');
                    }
                    fillFormTypes(subject);
                });
            }

            if (formTypeSelect) {
                formTypeSelect.addEventListener('change', function () {
                    setError('');
                    refreshInfo();
                });
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    const subject = selectedSubjectObject();
                    if (!groupSelect.value || !subject || !formTypeSelect.value) {
                        event.preventDefault();
                        setError("Guruh, fan va shaklni to'liq tanlang.");
                        return;
                    }
                });
            }
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
