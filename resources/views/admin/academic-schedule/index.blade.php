<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                YN kunini belgilash
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">

                @if(session('success'))
                    <div class="px-5 py-3 text-green-700 bg-green-50 border-b border-green-200" role="alert">
                        <strong class="font-bold">Muvaffaqiyat!</strong>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="px-5 py-3 text-red-700 bg-red-50 border-b border-red-200" role="alert">
                        <strong class="font-bold">Xato!</strong>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if(session('status'))
                    <div class="px-5 py-3 text-yellow-700 bg-yellow-50 border-b border-yellow-200" role="alert">
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="px-5 py-3 text-red-700 bg-red-50 border-b border-red-200" role="alert">
                        <strong class="font-bold">Xato!</strong>
                        <span>Ma'lumotlarni saqlashda xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.</span>
                    </div>
                @endif

                <!-- Filters -->
                <div class="filter-container">
                    <!-- Row 1: Ta'lim turi, Fakultet, Yo'nalish, Sanadan, Sanagacha, Joriy semestr -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="department_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Dars tugash (dan)</label>
                            <input type="text" id="date_from" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Dars tugash (gacha)</label>
                            <input type="text" id="date_to" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch {{ ($currentSemesterToggle ?? '1') === '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                        <div class="filter-item" style="min-width: 180px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch {{ request('show_students') === '1' ? 'active' : '' }}" id="show-students-toggle" onclick="toggleShowStudents()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Talabani ko'rsatish</span>
                            </div>
                        </div>
                    </div>
                    <!-- Row 2: Kurs, Semestr, Guruh, Fan, Holat, Qidirish -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 110px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0f172a;"></span> Fan</label>
                            <select id="subject_id" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Holat</label>
                            <select id="status" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="belgilangan" {{ ($selectedStatus ?? '') == 'belgilangan' ? 'selected' : '' }}>Belgilangan</option>
                                <option value="belgilanmagan" {{ ($selectedStatus ?? '') == 'belgilanmagan' ? 'selected' : '' }}>Belgilanmagan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#d97706;"></span> Urinish</label>
                            <select id="urinish_filter" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="1" {{ ($urinishFilter ?? '') === '1' ? 'selected' : '' }}>1-urinish</option>
                                <option value="2" {{ ($urinishFilter ?? '') === '2' ? 'selected' : '' }}>2-urinish</option>
                                <option value="3" {{ ($urinishFilter ?? '') === '3' ? 'selected' : '' }}>3-urinish</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" class="btn-calc" onclick="applyFilter()">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                Qidirish
                            </button>
                        </div>
                    </div>
                    <!-- Row 3: OSKI sanasi va Test sanasi filtrlari -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#7c3aed;"></span> OSKI sanasi (dan)</label>
                            <input type="text" id="oski_date_from" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#7c3aed;"></span> OSKI sanasi (gacha)</label>
                            <input type="text" id="oski_date_to" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0891b2;"></span> Test sanasi (dan)</label>
                            <input type="text" id="test_date_from" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0891b2;"></span> Test sanasi (gacha)</label>
                            <input type="text" id="test_date_to" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#059669;"></span> Yopilish shakli</label>
                            <select id="closing_form" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="unset" {{ ($selectedClosingForm ?? '') === 'unset' ? 'selected' : '' }}>Belgilanmagan</option>
                                <option value="oski" {{ ($selectedClosingForm ?? '') === 'oski' ? 'selected' : '' }}>Faqat OSKI</option>
                                <option value="test" {{ ($selectedClosingForm ?? '') === 'test' ? 'selected' : '' }}>Faqat Test</option>
                                <option value="oski_test" {{ ($selectedClosingForm ?? '') === 'oski_test' ? 'selected' : '' }}>OSKI + Test</option>
                                <option value="normativ" {{ ($selectedClosingForm ?? '') === 'normativ' ? 'selected' : '' }}>Normativ</option>
                                <option value="sinov" {{ ($selectedClosingForm ?? '') === 'sinov' ? 'selected' : '' }}>Sinov (test)</option>
                                <option value="none" {{ ($selectedClosingForm ?? '') === 'none' ? 'selected' : '' }}>Yo'q</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Test markazi yuklanganligi statistikasi -->
                @if($isSearched && !empty($testCenterLoad))
                    @php
                        $tcMaxTotal = 0;
                        foreach ($testCenterLoad as $d) { if ($d['total'] > $tcMaxTotal) $tcMaxTotal = $d['total']; }
                        // Sanani oraliqdagi umumiy holat
                        $tcDaysWithLoad = collect($testCenterLoad)->where('total', '>', 0)->count();
                        $tcEmptyDays = collect($testCenterLoad)->where('total', 0)->where('is_weekend', false)->count();
                        $tcTotalGroups = collect($testCenterLoad)->sum('group_count');
                    @endphp
                    <div class="tc-load-wrap">
                        <div class="tc-load-header">
                            <div class="tc-load-title">
                                <svg style="width:18px;height:18px;color:#1d4ed8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12-3a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Test markazi yuklanganligi</span>
                                <span class="tc-load-sub">— bo'sh kunlarni tanlash uchun</span>
                            </div>
                            <div class="tc-load-stats">
                                <span class="tc-stat-item"><b>{{ count($testCenterLoad) }}</b> kun</span>
                                <span class="tc-stat-sep">•</span>
                                <span class="tc-stat-item tc-stat-busy"><b>{{ $tcDaysWithLoad }}</b> band</span>
                                <span class="tc-stat-sep">•</span>
                                <span class="tc-stat-item tc-stat-free"><b>{{ $tcEmptyDays }}</b> bo'sh</span>
                                @if($tcMaxTotal > 0)
                                    <span class="tc-stat-sep">•</span>
                                    <span class="tc-stat-item">Eng band kun: <b>{{ $tcMaxTotal }}</b> imtihon</span>
                                @endif
                            </div>
                        </div>
                        <div class="tc-load-strip" id="tcLoadStrip">
                            @foreach($testCenterLoad as $d)
                                @php
                                    // Yuklanish darajasi: nisbiy (eng band kunga nisbatan)
                                    $ratio = $tcMaxTotal > 0 ? ($d['total'] / $tcMaxTotal) : 0;
                                    if ($d['total'] === 0) {
                                        $bg = $d['is_weekend'] ? '#f1f5f9' : '#f0fdf4';
                                        $border = $d['is_weekend'] ? '#e2e8f0' : '#bbf7d0';
                                        $loadClass = 'tc-empty';
                                    } elseif ($ratio < 0.34) {
                                        $bg = '#fefce8'; $border = '#fde68a'; $loadClass = 'tc-low';
                                    } elseif ($ratio < 0.67) {
                                        $bg = '#ffedd5'; $border = '#fdba74'; $loadClass = 'tc-mid';
                                    } else {
                                        $bg = '#fee2e2'; $border = '#fca5a5'; $loadClass = 'tc-high';
                                    }
                                    $dCarbon = \Carbon\Carbon::parse($d['date']);
                                    $tooltip = $dCarbon->format('d.m.Y') . ' (' . $dCarbon->isoFormat('dddd') . ")\n"
                                        . 'Guruhlar: ' . $d['group_count']
                                        . ' • OSKI: ' . $d['oski_count']
                                        . ' • Test: ' . $d['test_count']
                                        . ($d['student_count'] > 0 ? "\nTalabalar (asosiy YN): " . $d['student_count'] : '');
                                @endphp
                                <div class="tc-day {{ $loadClass }} {{ $d['is_weekend'] ? 'tc-weekend' : '' }}"
                                     style="background:{{ $bg }};border-color:{{ $border }};"
                                     title="{{ $tooltip }}">
                                    <div class="tc-day-date">{{ $dCarbon->format('d.m') }}</div>
                                    <div class="tc-day-wd">{{ $d['weekday'] }}</div>
                                    @if($d['total'] > 0)
                                        <div class="tc-day-total">{{ $d['total'] }}</div>
                                        <div class="tc-day-detail">
                                            @if($d['oski_count'] > 0)<span class="tc-pill tc-pill-o">O:{{ $d['oski_count'] }}</span>@endif
                                            @if($d['test_count'] > 0)<span class="tc-pill tc-pill-t">T:{{ $d['test_count'] }}</span>@endif
                                        </div>
                                    @else
                                        <div class="tc-day-total tc-day-empty">—</div>
                                        <div class="tc-day-detail tc-day-empty-label">{{ $d['is_weekend'] ? 'Dam' : "Bo'sh" }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="tc-load-legend">
                            <span class="tc-leg"><span class="tc-leg-sw" style="background:#f0fdf4;border-color:#bbf7d0;"></span> Bo'sh</span>
                            <span class="tc-leg"><span class="tc-leg-sw" style="background:#fefce8;border-color:#fde68a;"></span> Past</span>
                            <span class="tc-leg"><span class="tc-leg-sw" style="background:#ffedd5;border-color:#fdba74;"></span> O'rta</span>
                            <span class="tc-leg"><span class="tc-leg-sw" style="background:#fee2e2;border-color:#fca5a5;"></span> Yuqori</span>
                            <span class="tc-leg"><span class="tc-leg-sw" style="background:#f1f5f9;border-color:#e2e8f0;"></span> Dam olish kuni</span>
                        </div>
                    </div>
                @endif

                <!-- Results -->
                @if($scheduleData->count() > 0)
                @php
                    $unsetClosingFormCount = 0;
                    foreach ($scheduleData as $itemsBatch) {
                        foreach ($itemsBatch as $itemRow) {
                            if (($itemRow['closing_form'] ?? null) === null) {
                                $unsetClosingFormCount++;
                            }
                        }
                    }
                @endphp
                @if($unsetClosingFormCount > 0)
                    <div style="padding:10px 20px;background:#fef3c7;border-bottom:1px solid #fcd34d;color:#92400e;display:flex;align-items:center;gap:8px;font-size:13px;">
                        <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        <span>
                            <b>{{ $unsetClosingFormCount }}</b> ta fan uchun yopilish shakli belgilanmagan — OSKI va Test maydonlari ikkalasi ham ko'rinadi va N/A ni qo'lda belgilashingiz kerak bo'ladi.
                            <a href="{{ route('admin.closing-form.index') }}" target="_blank" style="text-decoration:underline;font-weight:600;color:#7c2d12;">Yopilish shaklini belgilash sahifasiga o'tish</a>
                        </span>
                    </div>
                @endif
                <form method="POST" action="{{ route($routePrefix . '.academic-schedule.store') }}">
                    @csrf
                    <div style="padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;font-weight:700;">
                                Imtihon sanalari
                                @if($currentEducationYear)
                                    @php
                                        $eduYearLabel = (string) $currentEducationYear;
                                        // Agar HEMIS API faqat boshlanish yili saqlasa (masalan "2025"),
                                        // ko'rinishni "2025-2026 o'quv yili" formatiga keltiramiz.
                                        if (preg_match('/^\d{4}$/', $eduYearLabel)) {
                                            $eduYearLabel = $eduYearLabel . '-' . ((int) $eduYearLabel + 1) . ' o\'quv yili';
                                        } elseif (!str_contains($eduYearLabel, 'o\'quv')) {
                                            $eduYearLabel = $eduYearLabel . ' o\'quv yili';
                                        }
                                    @endphp
                                    ({{ $eduYearLabel }})
                                @endif
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <a href="{{ route($routePrefix . '.academic-schedule.export-excel', request()->query()) }}" class="btn-excel-export" title="Filtrdan chiqqan natijalarni Excel ga yuklash">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Excel
                            </a>
                            <button type="submit" class="btn-save">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Saqlash
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th style="width:44px;padding-left:16px;">#</th>
                                    <th class="sortable" data-col="1">Guruh <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="2">Yo'nalish <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="3">Fan nomi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="4" style="width:70px;text-align:center;">Kredit <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="5" style="width:160px;text-align:center;">Dars boshlanish <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="6" style="width:160px;text-align:center;">Dars tugash <span class="sort-icon"></span></th>
                                    <th style="width:80px;text-align:center;">Urinish</th>
                                    <th class="sortable" data-col="8" style="width:90px;text-align:center;" title="Ushbu urinishda imtihon topshiradigan talabalar soni">Talaba soni <span class="sort-icon"></span></th>
                                    <th style="width:190px;text-align:center;">OSKI sanasi</th>
                                    <th style="width:190px;text-align:center;">Test sanasi</th>
                                </tr>
                            </thead>
                            <tbody id="schedule-tbody">
                                @php $rowIndex = 0; @endphp
                                @foreach($scheduleData as $groupHemisId => $items)
                                    @foreach($items as $item)
                                        @php
                                            // Virtual urinish-aware item (oski_date_for_urinish har doim set qilingan, fallback yo'q)
                                            $itemUrinish = $item['urinish'] ?? 1;
                                            $itemOski = $item['oski_date_for_urinish'] ?? null;
                                            $itemTest = $item['test_date_for_urinish'] ?? null;
                                            $itemOskiNa = $item['oski_na_for_urinish'] ?? false;
                                            $itemTestNa = $item['test_na_for_urinish'] ?? false;
                                            $oskiSaved = !empty($itemOski) || $itemOskiNa;
                                            $testSaved = !empty($itemTest) || $itemTestNa;
                                            // Yopilish shakli (KTR'da belgilanadi). Mos kelmaydigan tur bo'yicha
                                            // sana maydoni ko'rsatilmaydi va saqlashda avto N/A qo'yiladi.
                                            // Normativ va Sinov fanlar OSKI/Test orqali topshirilmaydi.
                                            $cf = $item['closing_form'] ?? null;
                                            $showOski = $cf === null || in_array($cf, ['oski', 'oski_test'], true);
                                            $showTest = $cf === null || in_array($cf, ['test', 'oski_test'], true);
                                            // Yopilish shakli belgilangan bo'lsa, N/A tugmasi kerak emas:
                                            // OSKI/Test'ning kerakliligi to'liq yopilish shakli orqali boshqariladi.
                                            $cfLocked = $cf !== null;
                                            $cfMeta = [
                                                'oski'      => ['label' => 'Faqat OSKI',  'bg' => '#dbeafe', 'fg' => '#1d4ed8', 'br' => '#bfdbfe'],
                                                'test'      => ['label' => 'Faqat Test',  'bg' => '#dcfce7', 'fg' => '#15803d', 'br' => '#bbf7d0'],
                                                'oski_test' => ['label' => 'OSKI + Test', 'bg' => '#ede9fe', 'fg' => '#6d28d9', 'br' => '#ddd6fe'],
                                                'normativ'  => ['label' => 'Normativ',    'bg' => '#fef3c7', 'fg' => '#a16207', 'br' => '#fde68a'],
                                                'sinov'     => ['label' => 'Sinov (test)','bg' => '#ffedd5', 'fg' => '#c2410c', 'br' => '#fed7aa'],
                                                'none'      => ['label' => "Yo'q",        'bg' => '#f1f5f9', 'fg' => '#475569', 'br' => '#cbd5e1'],
                                            ];
                                            $cfChip = $cfMeta[$cf] ?? null;
                                            $cfDashLabel = $cfChip['label'] ?? '';
                                        @endphp
                                        <tr class="data-row">
                                            <td class="row-num" style="color:#94a3b8;font-weight:500;padding-left:16px;">{{ ++$rowIndex }}</td>
                                            <td data-sort-value="{{ $item['group']->name }}" style="font-weight:600;color:#0f172a;">{{ $item['group']->name }}</td>
                                            <td data-sort-value="{{ $item['specialty_name'] }}" style="color:#64748b;font-size:12px;">{{ $item['specialty_name'] }}</td>
                                            <td data-sort-value="{{ $item['subject']->subject_name }}" style="font-weight:500;color:#1e293b;">
                                                <a href="{{ route('admin.journal.show', [$item['group']->id, $item['subject']->subject_id, $item['subject']->semester_code]) }}"
                                                   target="_blank"
                                                   class="hover:text-blue-600 hover:underline"
                                                   title="Jurnalni yangi oynada ochish">
                                                    {{ $item['subject']->subject_name }}
                                                </a>
                                                @if($cfChip)
                                                    <span class="cf-chip" style="background:{{ $cfChip['bg'] }};color:{{ $cfChip['fg'] }};border:1px solid {{ $cfChip['br'] }};" title="Yopilish shakli: {{ $cfChip['label'] }}">{{ $cfChip['label'] }}</span>
                                                @endif
                                            </td>
                                            <td data-sort-value="{{ $item['subject']->credit }}" style="text-align:center;color:#64748b;">{{ $item['subject']->credit }}</td>
                                            <td data-sort-value="{{ $item['lesson_start_date'] ? \Carbon\Carbon::parse($item['lesson_start_date'])->format('d.m.Y') : '' }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['lesson_start_date'])
                                                    <span class="lesson-date-badge">{{ \Carbon\Carbon::parse($item['lesson_start_date'])->format('d.m.Y') }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td data-sort-value="{{ $item['lesson_end_date'] ? \Carbon\Carbon::parse($item['lesson_end_date'])->format('d.m.Y') : '' }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['lesson_end_date'])
                                                    <span class="lesson-date-badge">{{ \Carbon\Carbon::parse($item['lesson_end_date'])->format('d.m.Y') }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td style="text-align:center;padding:4px 8px;">
                                                @php
                                                    $attemptColors = [1 => '#16a34a', 2 => '#d97706', 3 => '#ea580c'];
                                                    $attemptBgs = [1 => '#dcfce7', 2 => '#fef3c7', 3 => '#ffedd5'];
                                                @endphp
                                                <span style="display:inline-block;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;background:{{ $attemptBgs[$itemUrinish] }};color:{{ $attemptColors[$itemUrinish] }};">
                                                    {{ $itemUrinish }}-urinish
                                                </span>
                                            </td>
                                            @php $stuCnt = (int) ($item['student_count'] ?? 0); @endphp
                                            <td data-sort-value="{{ $stuCnt }}" style="text-align:center;padding:4px 8px;">
                                                @if($stuCnt > 0)
                                                    <span style="display:inline-block;min-width:28px;padding:2px 8px;border-radius:8px;font-size:12px;font-weight:600;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;" title="Ushbu urinishda imtihon topshiradigan talabalar soni">{{ $stuCnt }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;font-size:12px;">—</span>
                                                @endif
                                            </td>
                                            <td style="text-align:center;padding:4px 8px;">
                                                @if(!$showOski)
                                                    @if($cfChip)
                                                        <span class="cf-chip cf-chip-cell" style="background:{{ $cfChip['bg'] }};color:{{ $cfChip['fg'] }};border:1px solid {{ $cfChip['br'] }};" title="Bu fan uchun OSKI yo'q">{{ $cfChip['label'] }}</span>
                                                    @else
                                                        <span style="color:#cbd5e1;font-size:12px;">—</span>
                                                    @endif
                                                    <input type="hidden" name="schedules[{{ $rowIndex }}][oski_na]" value="1">
                                                @else
                                                <div class="exam-cell">
                                                    @if($oskiSaved && !($canEdit ?? false))
                                                        {{-- Saqlangan: o'zgartirib bo'lmaydi --}}
                                                        <div class="exam-date-wrap" id="oski_wrap_{{ $rowIndex }}" style="{{ $itemOskiNa ? 'display:none;' : '' }}">
                                                            <input type="text" class="date-input-masked date-input-locked" placeholder="kk.oo.yyyy"
                                                                   value="{{ $itemOski ? \Carbon\Carbon::parse($itemOski)->format('d.m.Y') : '' }}"
                                                                   maxlength="10" autocomplete="off" readonly
                                                                   title="Saqlangan sana o'zgartirib bo'lmaydi" />
                                                            <input type="hidden" name="schedules[{{ $rowIndex }}][oski_date]" id="oski_h_{{ $rowIndex }}" value="{{ $itemOski }}" />
                                                        </div>
                                                        @if(!$cfLocked)
                                                        <label class="na-toggle na-toggle-locked" title="Saqlangan holat o'zgartirib bo'lmaydi">
                                                            <input type="checkbox" name="schedules[{{ $rowIndex }}][oski_na]" value="1"
                                                                   {{ $itemOskiNa ? 'checked' : '' }} disabled>
                                                            <span class="na-label">N/A</span>
                                                        </label>
                                                        @endif
                                                        @if($itemOskiNa)
                                                            <input type="hidden" name="schedules[{{ $rowIndex }}][oski_na]" value="1">
                                                        @endif
                                                        <span class="lock-icon" title="Saqlangan sana o'zgartirib bo'lmaydi">🔒</span>
                                                        @if($canDelete)
                                                        <button type="button" class="clear-date-btn" title="OSKI sanasini o'chirish"
                                                                onclick="clearExamDate('{{ $item['group']->group_hemis_id }}', '{{ $item['subject']->subject_id }}', '{{ $item['subject']->semester_code }}', 'oski')">
                                                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                        @endif
                                                    @elseif($oskiSaved && ($canEdit ?? false))
                                                        {{-- Saqlangan lekin o'zgartirish mumkin (boshlig'i/admin) --}}
                                                        <div class="exam-date-wrap" id="oski_wrap_{{ $rowIndex }}" style="{{ $itemOskiNa ? 'display:none;' : '' }}">
                                                            <input type="text" id="oski_cal_{{ $rowIndex }}" name="schedules[{{ $rowIndex }}][oski_date]"
                                                                   class="exam-sc-date" autocomplete="off"
                                                                   data-initial-value="{{ $itemOski ? \Carbon\Carbon::parse($itemOski)->format('Y-m-d') : '' }}" />
                                                        </div>
                                                        @if(!$cfLocked)
                                                        <label class="na-toggle" title="Bu fan uchun OSKI yo'q">
                                                            <input type="checkbox" name="schedules[{{ $rowIndex }}][oski_na]" value="1"
                                                                   {{ $itemOskiNa ? 'checked' : '' }}
                                                                   onchange="toggleNa(this, 'oski_wrap_{{ $rowIndex }}')">
                                                            <span class="na-label">N/A</span>
                                                        </label>
                                                        @endif
                                                        @if($canDelete)
                                                        <button type="button" class="clear-date-btn" title="OSKI sanasini o'chirish"
                                                                onclick="clearExamDate('{{ $item['group']->group_hemis_id }}', '{{ $item['subject']->subject_id }}', '{{ $item['subject']->semester_code }}', 'oski')">
                                                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                        @endif
                                                    @else
                                                        <div class="exam-date-wrap" id="oski_wrap_{{ $rowIndex }}">
                                                            <input type="text" id="oski_cal_{{ $rowIndex }}" name="schedules[{{ $rowIndex }}][oski_date]"
                                                                   class="exam-sc-date" autocomplete="off" />
                                                        </div>
                                                        @if(!$cfLocked)
                                                        <label class="na-toggle" title="Bu fan uchun OSKI yo'q">
                                                            <input type="checkbox" name="schedules[{{ $rowIndex }}][oski_na]" value="1"
                                                                   onchange="toggleNa(this, 'oski_wrap_{{ $rowIndex }}')">
                                                            <span class="na-label">N/A</span>
                                                        </label>
                                                        @endif
                                                    @endif
                                                </div>
                                                @endif
                                            </td>
                                            <td style="text-align:center;padding:4px 8px;">
                                                @if(!$showTest)
                                                    @if($cfChip)
                                                        <span class="cf-chip cf-chip-cell" style="background:{{ $cfChip['bg'] }};color:{{ $cfChip['fg'] }};border:1px solid {{ $cfChip['br'] }};" title="Bu fan uchun Test yo'q">{{ $cfChip['label'] }}</span>
                                                    @else
                                                        <span style="color:#cbd5e1;font-size:12px;">—</span>
                                                    @endif
                                                    <input type="hidden" name="schedules[{{ $rowIndex }}][test_na]" value="1">
                                                @else
                                                <div class="exam-cell">
                                                    @if($testSaved && !($canEdit ?? false))
                                                        {{-- Saqlangan: o'zgartirib bo'lmaydi --}}
                                                        <div class="exam-date-wrap" id="test_wrap_{{ $rowIndex }}" style="{{ $itemTestNa ? 'display:none;' : '' }}">
                                                            <input type="text" class="date-input-masked date-input-locked" placeholder="kk.oo.yyyy"
                                                                   value="{{ $itemTest ? \Carbon\Carbon::parse($itemTest)->format('d.m.Y') : '' }}"
                                                                   maxlength="10" autocomplete="off" readonly
                                                                   title="Saqlangan sana o'zgartirib bo'lmaydi" />
                                                            <input type="hidden" name="schedules[{{ $rowIndex }}][test_date]" id="test_h_{{ $rowIndex }}" value="{{ $itemTest }}" />
                                                        </div>
                                                        @if(!$cfLocked)
                                                        <label class="na-toggle na-toggle-locked" title="Saqlangan holat o'zgartirib bo'lmaydi">
                                                            <input type="checkbox" name="schedules[{{ $rowIndex }}][test_na]" value="1"
                                                                   {{ $itemTestNa ? 'checked' : '' }} disabled>
                                                            <span class="na-label">N/A</span>
                                                        </label>
                                                        @endif
                                                        @if($itemTestNa)
                                                            <input type="hidden" name="schedules[{{ $rowIndex }}][test_na]" value="1">
                                                        @endif
                                                        <span class="lock-icon" title="Saqlangan sana o'zgartirib bo'lmaydi">🔒</span>
                                                        @if($canDelete)
                                                        <button type="button" class="clear-date-btn" title="Test sanasini o'chirish"
                                                                onclick="clearExamDate('{{ $item['group']->group_hemis_id }}', '{{ $item['subject']->subject_id }}', '{{ $item['subject']->semester_code }}', 'test')">
                                                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                        @endif
                                                    @elseif($testSaved && ($canEdit ?? false))
                                                        {{-- Saqlangan lekin o'zgartirish mumkin (boshlig'i/admin) --}}
                                                        <div class="exam-date-wrap" id="test_wrap_{{ $rowIndex }}" style="{{ $itemTestNa ? 'display:none;' : '' }}">
                                                            <input type="text" id="test_cal_{{ $rowIndex }}" name="schedules[{{ $rowIndex }}][test_date]"
                                                                   class="exam-sc-date" autocomplete="off"
                                                                   data-initial-value="{{ $itemTest ? \Carbon\Carbon::parse($itemTest)->format('Y-m-d') : '' }}" />
                                                        </div>
                                                        @if(!$cfLocked)
                                                        <label class="na-toggle" title="Bu fan uchun Test yo'q">
                                                            <input type="checkbox" name="schedules[{{ $rowIndex }}][test_na]" value="1"
                                                                   {{ $itemTestNa ? 'checked' : '' }}
                                                                   onchange="toggleNa(this, 'test_wrap_{{ $rowIndex }}')">
                                                            <span class="na-label">N/A</span>
                                                        </label>
                                                        @endif
                                                        @if($canDelete)
                                                        <button type="button" class="clear-date-btn" title="Test sanasini o'chirish"
                                                                onclick="clearExamDate('{{ $item['group']->group_hemis_id }}', '{{ $item['subject']->subject_id }}', '{{ $item['subject']->semester_code }}', 'test')">
                                                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                        @endif
                                                    @else
                                                        <div class="exam-date-wrap" id="test_wrap_{{ $rowIndex }}">
                                                            <input type="text" id="test_cal_{{ $rowIndex }}" name="schedules[{{ $rowIndex }}][test_date]"
                                                                   class="exam-sc-date" autocomplete="off" />
                                                        </div>
                                                        @if(!$cfLocked)
                                                        <label class="na-toggle" title="Bu fan uchun Test yo'q">
                                                            <input type="checkbox" name="schedules[{{ $rowIndex }}][test_na]" value="1"
                                                                   onchange="toggleNa(this, 'test_wrap_{{ $rowIndex }}')">
                                                            <span class="na-label">N/A</span>
                                                        </label>
                                                        @endif
                                                    @endif
                                                </div>
                                                @endif
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][group_hemis_id]" value="{{ $item['group']->group_hemis_id }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][subject_id]" value="{{ $item['subject']->subject_id }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][subject_name]" value="{{ $item['subject']->subject_name }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][department_hemis_id]" value="{{ $item['group']->department_hemis_id }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][specialty_hemis_id]" value="{{ $item['group']->specialty_hemis_id }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][curriculum_hemis_id]" value="{{ $item['group']->curriculum_hemis_id }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][semester_code]" value="{{ $item['subject']->semester_code }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][urinish]" value="{{ $itemUrinish }}">
                                                <input type="hidden" name="schedules[{{ $rowIndex }}][closing_form]" value="{{ $cf }}">
                                            </td>
                                        </tr>

                                        {{-- Per-student qatorlar — har urinish (1/2/3) ostida.
                                             1-urinish: barcha talabalar (har biriga individual sana mumkin)
                                             2-urinish: faqat 1-urinishdan o'tmaganlar (V<60). Pullik bo'lsa sana yopiq.
                                             3-urinish: faqat 12a dan o'tmaganlar. Pullik bo'lsa sana yopiq. --}}
                                        @php
                                            $studentsForRow = $item['students'] ?? [];
                                            if ($itemUrinish === 2) {
                                                $studentsForRow = array_values(array_filter($studentsForRow, fn($s) => !empty($s['failed_attempt1'])));
                                            } elseif ($itemUrinish === 3) {
                                                $studentsForRow = array_values(array_filter($studentsForRow, fn($s) => !empty($s['failed_attempt2'])));
                                            }
                                        @endphp
                                        @if(($showStudents ?? false) && !empty($studentsForRow))
                                            @foreach($studentsForRow as $stuRow)
                                                @php
                                                    $rowIndex++;
                                                    if ($itemUrinish === 1) {
                                                        $stuValueOski = $stuRow['oski_date'] ?? '';
                                                        $stuValueTest = $stuRow['test_date'] ?? '';
                                                    } elseif ($itemUrinish === 2) {
                                                        $stuValueOski = $stuRow['oski_resit_date'] ?? '';
                                                        $stuValueTest = $stuRow['test_resit_date'] ?? '';
                                                    } else {
                                                        $stuValueOski = $stuRow['oski_resit2_date'] ?? '';
                                                        $stuValueTest = $stuRow['test_resit2_date'] ?? '';
                                                    }
                                                    // Qarz fanlari: o'tgan semestrlardagi (academic_records'da yo'q)
                                                    // + joriy semestrdagi BARCHA failed fanlar (controller pre-pass'dan)
                                                    $stuPastDebts = $stuRow['past_debts'] ?? [];
                                                    $stuCurrentDebts = $stuRow['current_semester_debts'] ?? [];
                                                    $stuDebtCount = count($stuPastDebts) + count($stuCurrentDebts);
                                                    $stuDebtTooltip = '';
                                                    if ($stuDebtCount > 0) {
                                                        $tooltipLines = [];
                                                        foreach ($stuPastDebts as $d) {
                                                            $tooltipLines[] = '• ' . ($d['subject_name'] ?? '') . ' (' . ($d['semester_name'] ?? '') . ')';
                                                        }
                                                        foreach ($stuCurrentDebts as $d) {
                                                            $tooltipLines[] = '• ' . ($d['subject_name'] ?? '') . ' (' . ($d['semester_name'] ?? '') . ') — joriy';
                                                        }
                                                        $stuDebtTooltip = implode("\n", $tooltipLines);
                                                    }
                                                    // Pullik (jn/mt past yoki davomat ≥25%) → 2/3-urinishda sana qo'yib bo'lmaydi
                                                    $pullikBlocked = ($itemUrinish > 1) && !empty($stuRow['is_pullik']);
                                                    // 4+ ta fandan qarz → kursdan qoldiriladi, qayta topshira olmaydi
                                                    // (eski is_held_back yoki yangi total qarz soni >= 4 — qaysi biri bo'lsa)
                                                    $isHeldBack = !empty($stuRow['is_held_back']) || $stuDebtCount >= 4;
                                                    $heldBackBlocked = ($itemUrinish > 1) && $isHeldBack;
                                                    $isBlocked = $pullikBlocked || $heldBackBlocked;
                                                    $blockedTitle = $heldBackBlocked
                                                        ? '4 tadan ortiq fandan qarz — kursdan qoldiriladi, qayta topshira olmaydi'
                                                        : 'Pullik talaba — sana belgilab bo\'lmaydi';
                                                @endphp
                                                <tr class="student-sub-row" style="background:{{ $isBlocked ? '#fef2f2' : '#fafafa' }};border-top:1px dashed #e2e8f0;">
                                                    <td></td>
                                                    <td colspan="6" style="padding:4px 8px 4px 40px;font-size:11px;color:{{ $isBlocked ? '#991b1b' : '#475569' }};">
                                                        <span style="display:inline-block;padding:0 4px;border-left:3px solid {{ $isBlocked ? '#fca5a5' : '#93c5fd' }};margin-right:6px;">↳</span>
                                                        {{ $stuRow['full_name'] }}
                                                        @if($stuDebtCount > 0)
                                                            <span class="debt-badge" title="{{ $stuDebtTooltip }}">{{ $stuDebtCount }} qarz</span>
                                                        @endif
                                                        @if($heldBackBlocked)
                                                            <span style="margin-left:6px;padding:1px 5px;border-radius:6px;font-size:9px;font-weight:600;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;" title="4 tadan ortiq fandan qarz — kursdan qoldiriladi">4 tadan ortiq qarz</span>
                                                        @elseif($pullikBlocked)
                                                            <span style="margin-left:6px;padding:1px 5px;border-radius:6px;font-size:9px;font-weight:600;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;" title="JN/MT past yoki davomat ≥25% — qayta topshira olmaydi (pullik)">Pullik</span>
                                                        @endif
                                                    </td>
                                                    <td style="text-align:center;font-size:9px;color:#64748b;">
                                                        @php
                                                            $stuBadgeBg = $itemUrinish === 1 ? '#dcfce7' : ($itemUrinish === 3 ? '#ffedd5' : '#fef3c7');
                                                            $stuBadgeFg = $itemUrinish === 1 ? '#16a34a' : ($itemUrinish === 3 ? '#ea580c' : '#d97706');
                                                            $stuBorderColor = $itemUrinish === 1 ? '#86efac' : ($itemUrinish === 3 ? '#fdba74' : '#fcd34d');
                                                        @endphp
                                                        <span style="display:inline-block;padding:1px 5px;border-radius:6px;font-size:9px;font-weight:600;background:{{ $stuBadgeBg }};color:{{ $stuBadgeFg }};">{{ $itemUrinish }}-urinish</span>
                                                    </td>
                                                    <td></td>
                                                    <td style="text-align:center;padding:4px 8px;">
                                                        @if(!$showOski)
                                                            <span style="color:#cbd5e1;font-size:10px;">—</span>
                                                        @elseif($isBlocked)
                                                            <input type="text" placeholder="kk.oo.yyyy" maxlength="10" readonly disabled
                                                                   value="{{ $stuValueOski ? \Carbon\Carbon::parse($stuValueOski)->format('d.m.Y') : '' }}"
                                                                   title="{{ $blockedTitle }}"
                                                                   style="font-size:10px; padding:2px 4px; border:1px solid #fca5a5; border-radius:4px; max-width:135px; background:#fee2e2;color:#991b1b;cursor:not-allowed;" />
                                                        @else
                                                            <input type="text" id="stu_oski_{{ $rowIndex }}"
                                                                   name="schedules[{{ $rowIndex }}][oski_date]"
                                                                   class="exam-sc-date" autocomplete="off"
                                                                   data-initial-value="{{ $stuValueOski }}"
                                                                   title="{{ $itemUrinish }}-urinish OSKI sanasi"
                                                                   style="font-size:10px; padding:2px 4px; border:1px solid {{ $stuBorderColor }}; border-radius:4px; max-width:135px;" />
                                                        @endif
                                                    </td>
                                                    <td style="text-align:center;padding:4px 8px;">
                                                        @if(!$showTest)
                                                            <span style="color:#cbd5e1;font-size:10px;">—</span>
                                                        @elseif($isBlocked)
                                                            <input type="text" placeholder="kk.oo.yyyy" maxlength="10" readonly disabled
                                                                   value="{{ $stuValueTest ? \Carbon\Carbon::parse($stuValueTest)->format('d.m.Y') : '' }}"
                                                                   title="{{ $blockedTitle }}"
                                                                   style="font-size:10px; padding:2px 4px; border:1px solid #fca5a5; border-radius:4px; max-width:135px; background:#fee2e2;color:#991b1b;cursor:not-allowed;" />
                                                        @else
                                                            <input type="text" id="stu_test_{{ $rowIndex }}"
                                                                   name="schedules[{{ $rowIndex }}][test_date]"
                                                                   class="exam-sc-date" autocomplete="off"
                                                                   data-initial-value="{{ $stuValueTest }}"
                                                                   title="{{ $itemUrinish }}-urinish Test sanasi"
                                                                   style="font-size:10px; padding:2px 4px; border:1px solid {{ $stuBorderColor }}; border-radius:4px; max-width:135px;" />
                                                        @endif
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][urinish]" value="{{ $itemUrinish }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][closing_form]" value="{{ $cf }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][group_hemis_id]" value="{{ $item['group']->group_hemis_id }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][student_hemis_id]" value="{{ $stuRow['hemis_id'] }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][subject_id]" value="{{ $item['subject']->subject_id }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][subject_name]" value="{{ $item['subject']->subject_name }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][department_hemis_id]" value="{{ $item['group']->department_hemis_id }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][specialty_hemis_id]" value="{{ $item['group']->specialty_hemis_id }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][curriculum_hemis_id]" value="{{ $item['group']->curriculum_hemis_id }}">
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][semester_code]" value="{{ $item['subject']->semester_code }}">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="10" style="padding:8px 16px;font-size:12px;color:#94a3b8;text-align:right;">Jami: {{ $scheduleData->flatten(1)->count() }} ta fan</td></tr>
                            </tfoot>
                        </table>
                    </div>

                    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn-save">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Saqlash
                        </button>
                    </div>
                </form>
                @elseif($isSearched)
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Ma'lumot topilmadi</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Tanlangan filtrlar bo'yicha fanlar topilmadi.</p>
                    </div>
                @else
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Qidirish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Natijalar shu yerda ko'rsatiladi</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- O'chirish form'i - asosiy store form'idan tashqarida (ichma-ich form HTML da ruxsat etilmaydi) --}}
    <form id="clear-date-form" method="POST" action="{{ route(($routePrefix ?? 'admin') . '.academic-schedule.clear-date') }}" style="display:none;">
        @csrf
        <input type="hidden" name="group_hemis_id" id="clear-group-hemis-id">
        <input type="hidden" name="subject_id" id="clear-subject-id">
        <input type="hidden" name="semester_code" id="clear-semester-code">
        <input type="hidden" name="date_type" id="clear-date-type">
    </form>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <script>
        function clearExamDate(groupHemisId, subjectId, semesterCode, dateType) {
            var typeLabel = dateType === 'oski' ? 'OSKI' : 'Test';
            if (!confirm(typeLabel + ' sanasini o\'chirishni tasdiqlaysizmi?')) return;
            document.getElementById('clear-group-hemis-id').value = groupHemisId;
            document.getElementById('clear-subject-id').value = subjectId;
            document.getElementById('clear-semester-code').value = semesterCode;
            document.getElementById('clear-date-type').value = dateType;
            document.getElementById('clear-date-form').submit();
        }

        var isUpdatingFilters = false;
        var filterUrl = '{{ route($routePrefix . ".academic-schedule.get-filter-options") }}';

        // Initial values from server (sahifa qayta yuklanganda)
        var initialValues = {
            education_type: '{{ $selectedEducationType ?? '' }}',
            department_id: '{{ $selectedDepartment ?? '' }}',
            specialty_id: '{{ $selectedSpecialty ?? '' }}',
            level_code: '{{ $selectedLevelCode ?? '' }}',
            semester_code: '{{ $selectedSemester ?? '' }}',
            group_id: '{{ $selectedGroup ?? '' }}',
            subject_id: '{{ $selectedSubject ?? '' }}',
            current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0'
        };

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function toggleSemester() {
            var btn = document.getElementById('current-semester-toggle');
            btn.classList.toggle('active');
            // Toggle o'zgarganda filtrlarni qayta yuklash
            if (!isUpdatingFilters) loadAllFilters();
        }

        function toggleNa(checkbox, wrapId) {
            var wrap = document.getElementById(wrapId);
            if (checkbox.checked) {
                wrap.style.display = 'none';
                // SC input tozalash
                var scHidden = wrap.querySelector('input[type="hidden"].exam-sc-date');
                var scDisplay = wrap.querySelector('.sc-wrap .date-input');
                var scClear = wrap.querySelector('.sc-clear');
                if (scHidden) scHidden.value = '';
                if (scDisplay) scDisplay.value = '';
                if (scClear) scClear.style.display = 'none';
            } else {
                wrap.style.display = '';
            }
        }

        // dd.mm.yyyy mask va validatsiya
        function initDateMask() {
            document.querySelectorAll('.date-input-masked').forEach(function(inp) {
                // Faqat raqam va nuqta qo'yish
                inp.addEventListener('input', function(e) {
                    var v = this.value.replace(/[^\d]/g, '');
                    if (v.length > 8) v = v.substring(0, 8);
                    var parts = [];
                    if (v.length > 0) parts.push(v.substring(0, Math.min(2, v.length)));
                    if (v.length > 2) parts.push(v.substring(2, Math.min(4, v.length)));
                    if (v.length > 4) parts.push(v.substring(4, 8));
                    this.value = parts.join('.');
                    syncHidden(this);
                });

                // Blur da tekshirish
                inp.addEventListener('blur', function() {
                    validateDateInput(this);
                });
            });
        }

        function validateDateInput(inp) {
            var v = inp.value.trim();
            if (!v) {
                inp.classList.remove('date-error');
                inp.title = '';
                syncHidden(inp);
                return true;
            }
            var m = v.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
            if (!m) {
                inp.classList.add('date-error');
                inp.title = 'Format: kk.oo.yyyy';
                clearHidden(inp);
                return false;
            }
            var day = parseInt(m[1], 10);
            var month = parseInt(m[2], 10);
            var year = parseInt(m[3], 10);
            var err = '';
            if (month < 1 || month > 12) err = 'Oy 01-12 orasida bo\'lishi kerak';
            else if (day < 1 || day > 31) err = 'Kun 01-31 orasida bo\'lishi kerak';
            else {
                // Oydagi kunlar sonini tekshirish
                var maxDay = new Date(year, month, 0).getDate();
                if (day > maxDay) err = month + '-oyda ' + maxDay + ' kun bor';
            }
            if (year < 2020 || year > 2040) err = 'Yil 2020-2040 orasida bo\'lishi kerak';
            if (!err) {
                // Cheklov 2: imtihon sanasi kamida ertadan bo'lishi kerak.
                // Agar sozlamalarda "Eski sanalarni qo'yishga ruxsat" yoqilgan bo'lsa — tekshiruv chetlab o'tiladi.
                @if(!($allowPastExamDates ?? false))
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var inputDate = new Date(year, month - 1, day);
                @if(($isAdmin ?? false) || ($allowTodayExamDates ?? false))
                // Admin yoki "Bugunni qo'yish" toggle yoqilgan — bugun ham mumkin,
                // faqat o'tib ketgan kunlar bloklanadi.
                if (inputDate < today) {
                    err = 'Imtihon sanasi o\'tgan kunni qo\'yib bo\'lmaydi';
                }
                @else
                if (inputDate <= today) {
                    err = 'Imtihon sanasi kamida ertadan bo\'lishi kerak (bugun qo\'yib bo\'lmaydi)';
                }
                // Cheklov 3: ertangi kunga sana belgilash uchun bugungi soat cutoff
                // (default 18:00). Shu tariqa Test markaziga vaqtlarni belgilashga
                // muddat qoladi. "Bugunni qo'yish" yoqilgan bo'lsa, cutoff ham o'chiriladi.
                if (!err) {
                    var cutoffHour = {{ (int) ($examDateSubmissionCutoffHour ?? 18) }};
                    var tomorrow = new Date();
                    tomorrow.setHours(0, 0, 0, 0);
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    if (inputDate.getTime() === tomorrow.getTime() && new Date().getHours() >= cutoffHour) {
                        var hh = cutoffHour < 10 ? '0' + cutoffHour : '' + cutoffHour;
                        err = 'Ertangi kunga sana belgilash uchun soat ' + hh + ':00 o\'tib ketgan. Iltimos, kelgusi kunlardan birini tanlang.';
                    }
                }
                @endif
                @endif
            }
            if (err) {
                inp.classList.add('date-error');
                inp.title = err;
                clearHidden(inp);
                return false;
            }
            inp.classList.remove('date-error');
            inp.title = '';
            syncHidden(inp);
            return true;
        }

        function syncHidden(inp) {
            var hidId = inp.getAttribute('data-hidden');
            if (!hidId) return;
            var hid = document.getElementById(hidId);
            if (!hid) return;
            var v = inp.value.trim();
            var m = v.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
            hid.value = m ? (m[3] + '-' + m[2] + '-' + m[1]) : '';
        }

        function clearHidden(inp) {
            var hidId = inp.getAttribute('data-hidden');
            if (!hidId) return;
            var hid = document.getElementById(hidId);
            if (hid) hid.value = '';
        }

        // Jadval ustunlarini bosganda sort
        var currentSortCol = -1;
        var currentSortDir = 'asc';

        function initTableSort() {
            document.querySelectorAll('.sortable').forEach(function(th) {
                th.addEventListener('click', function() {
                    var col = parseInt(this.getAttribute('data-col'));
                    if (currentSortCol === col) {
                        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        currentSortCol = col;
                        currentSortDir = 'asc';
                    }
                    sortTable(col, currentSortDir);
                    // Icon yangilash
                    document.querySelectorAll('.sortable .sort-icon').forEach(function(s) { s.textContent = ''; });
                    this.querySelector('.sort-icon').textContent = currentSortDir === 'asc' ? ' \u25B2' : ' \u25BC';
                });
            });
        }

        function sortTable(colIndex, dir) {
            var tbody = document.getElementById('schedule-tbody');
            if (!tbody) return;
            var rows = Array.from(tbody.querySelectorAll('tr.data-row'));
            rows.sort(function(a, b) {
                var aCell = a.cells[colIndex];
                var bCell = b.cells[colIndex];
                var aVal = (aCell && aCell.getAttribute('data-sort-value')) || '';
                var bVal = (bCell && bCell.getAttribute('data-sort-value')) || '';
                // Faqat toza raqamlarni raqam sifatida sort (kredit)
                if (/^\d+(\.\d+)?$/.test(aVal) && /^\d+(\.\d+)?$/.test(bVal)) {
                    return dir === 'asc' ? parseFloat(aVal) - parseFloat(bVal) : parseFloat(bVal) - parseFloat(aVal);
                }
                // dd.mm.yyyy sana formatini aniqlash va sort qilish
                var dateRe = /^(\d{2})\.(\d{2})\.(\d{4})$/;
                var aM = aVal.match(dateRe);
                var bM = bVal.match(dateRe);
                if (aM && bM) {
                    var aD = aM[3] + aM[2] + aM[1];
                    var bD = bM[3] + bM[2] + bM[1];
                    return dir === 'asc' ? aD.localeCompare(bD) : bD.localeCompare(aD);
                }
                // Bo'sh sanalarni oxiriga surish
                if (aM && !bM) return dir === 'asc' ? -1 : 1;
                if (!aM && bM) return dir === 'asc' ? 1 : -1;
                // Matn tartiblash
                var cmp = aVal.localeCompare(bVal, 'uz');
                return dir === 'asc' ? cmp : -cmp;
            });
            // Qayta joylashtirish va raqamlarni yangilash
            rows.forEach(function(row, i) {
                tbody.appendChild(row);
                var numCell = row.querySelector('.row-num');
                if (numCell) numCell.textContent = i + 1;
            });
        }

        // Dropdown parametrlarini yig'ish
        function fp() {
            return {
                education_type: $('#education_type').val() || '',
                department_id: $('#department_id').val() || '',
                specialty_id: $('#specialty_id').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0'
            };
        }

        // Dropdown ni yangilash (tanlangan qiymatni saqlab)
        function updateSelect(selector, items, valueKey, textKey) {
            var $el = $(selector);
            var currentVal = $el.val();
            $el.empty().append('<option value="">Barchasi</option>');
            $.each(items, function(i, item) {
                $el.append('<option value="' + item[valueKey] + '">' + item[textKey] + '</option>');
            });
            // Agar avvalgi qiymat hali ham mavjud bo'lsa - tiklash
            if (currentVal && $el.find('option[value="' + currentVal + '"]').length) {
                $el.val(currentVal);
            }
            $el.trigger('change.select2');
        }

        // Bidirectional filter: barcha dropdownlarni yangilash
        function loadAllFilters(callback) {
            if (isUpdatingFilters) return;
            isUpdatingFilters = true;

            $.get(filterUrl, fp(), function(data) {
                updateSelect('#education_type', data.educationTypes, 'education_type_code', 'education_type_name');
                updateSelect('#department_id', data.departments, 'department_hemis_id', 'name');
                updateSelect('#specialty_id', data.specialties, 'specialty_hemis_id', 'name');
                updateSelect('#level_code', data.levels, 'level_code', 'level_name');
                updateSelect('#semester_code', data.semesters, 'code', 'name');
                updateSelect('#group_id', data.groups, 'group_hemis_id', 'name');
                updateSelect('#subject_id', data.subjects, 'subject_id', 'subject_name');

                isUpdatingFilters = false;
                if (callback) callback();
            }).fail(function() {
                isUpdatingFilters = false;
            });
        }

        // Boshlang'ich yuklash (query params bor bo'lsa qiymatlarni tiklash)
        function initFilters() {
            isUpdatingFilters = true;
            $.get(filterUrl, initialValues, function(data) {
                updateSelect('#education_type', data.educationTypes, 'education_type_code', 'education_type_name');
                updateSelect('#department_id', data.departments, 'department_hemis_id', 'name');
                updateSelect('#specialty_id', data.specialties, 'specialty_hemis_id', 'name');
                updateSelect('#level_code', data.levels, 'level_code', 'level_name');
                updateSelect('#semester_code', data.semesters, 'code', 'name');
                updateSelect('#group_id', data.groups, 'group_hemis_id', 'name');
                updateSelect('#subject_id', data.subjects, 'subject_id', 'subject_name');

                // Initial qiymatlarni tiklash
                if (initialValues.education_type) $('#education_type').val(initialValues.education_type).trigger('change.select2');
                if (initialValues.department_id) $('#department_id').val(initialValues.department_id).trigger('change.select2');
                if (initialValues.specialty_id) $('#specialty_id').val(initialValues.specialty_id).trigger('change.select2');
                if (initialValues.level_code) $('#level_code').val(initialValues.level_code).trigger('change.select2');
                if (initialValues.semester_code) $('#semester_code').val(initialValues.semester_code).trigger('change.select2');
                if (initialValues.group_id) $('#group_id').val(initialValues.group_id).trigger('change.select2');
                if (initialValues.subject_id) $('#subject_id').val(initialValues.subject_id).trigger('change.select2');

                isUpdatingFilters = false;
            }).fail(function() {
                isUpdatingFilters = false;
            });
        }

        function applyFilter() {
            var url = new URL(window.location.href.split('?')[0]);
            url.searchParams.set('searched', '1');
            var et = $('#education_type').val();
            var dept = $('#department_id').val();
            var spec = $('#specialty_id').val();
            var lc = $('#level_code').val();
            var sem = $('#semester_code').val();
            var grp = $('#group_id').val();
            var subj = $('#subject_id').val();
            var status = $('#status').val();
            var cs = document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0';
            var dateFrom = $('#date_from').val();
            var dateTo = $('#date_to').val();
            var oskiDateFrom = $('#oski_date_from').val();
            var oskiDateTo = $('#oski_date_to').val();
            var testDateFrom = $('#test_date_from').val();
            var testDateTo = $('#test_date_to').val();
            if (et) url.searchParams.set('education_type', et);
            if (dept) url.searchParams.set('department_id', dept);
            if (spec) url.searchParams.set('specialty_id', spec);
            if (lc) url.searchParams.set('level_code', lc);
            if (sem) url.searchParams.set('semester_code', sem);
            if (grp) url.searchParams.set('group_id', grp);
            if (subj) url.searchParams.set('subject_id', subj);
            if (status) url.searchParams.set('status', status);
            if (dateFrom) url.searchParams.set('date_from', dateFrom);
            if (dateTo) url.searchParams.set('date_to', dateTo);
            if (oskiDateFrom) url.searchParams.set('oski_date_from', oskiDateFrom);
            if (oskiDateTo) url.searchParams.set('oski_date_to', oskiDateTo);
            if (testDateFrom) url.searchParams.set('test_date_from', testDateFrom);
            if (testDateTo) url.searchParams.set('test_date_to', testDateTo);
            url.searchParams.set('current_semester', cs);
            var showStudents = document.getElementById('show-students-toggle')?.classList.contains('active') ? '1' : '0';
            if (showStudents === '1') url.searchParams.set('show_students', '1');
            var urinishVal = $('#urinish_filter').val();
            if (urinishVal) url.searchParams.set('urinish', urinishVal);
            var cf = $('#closing_form').val();
            if (cf) url.searchParams.set('closing_form', cf);
            window.location.href = url.toString();
        }

        function toggleShowStudents() {
            var btn = document.getElementById('show-students-toggle');
            btn.classList.toggle('active');
        }

        $(document).ready(function() {
            // Select2 init
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Bidirectional: har qanday asosiy filtr o'zgarganda barcha filtrlarni yangilash
            $('#education_type, #department_id, #specialty_id, #level_code, #semester_code').on('change', function() {
                if (!isUpdatingFilters) loadAllFilters();
            });

            // Init
            initFilters();

            // Scroll calendar for date filters
            var calFrom = new ScrollCalendar('date_from');
            var calTo = new ScrollCalendar('date_to');
            var calOskiFrom = new ScrollCalendar('oski_date_from');
            var calOskiTo = new ScrollCalendar('oski_date_to');
            var calTestFrom = new ScrollCalendar('test_date_from');
            var calTestTo = new ScrollCalendar('test_date_to');
            @if(request()->get('date_from'))
                calFrom.setValue('{{ request()->get("date_from") }}');
            @endif
            @if(request()->get('date_to'))
                calTo.setValue('{{ request()->get("date_to") }}');
            @endif
            @if(request()->get('oski_date_from'))
                calOskiFrom.setValue('{{ request()->get("oski_date_from") }}');
            @endif
            @if(request()->get('oski_date_to'))
                calOskiTo.setValue('{{ request()->get("oski_date_to") }}');
            @endif
            @if(request()->get('test_date_from'))
                calTestFrom.setValue('{{ request()->get("test_date_from") }}');
            @endif
            @if(request()->get('test_date_to'))
                calTestTo.setValue('{{ request()->get("test_date_to") }}');
            @endif

            // Jadval sana inputlari uchun ScrollCalendar ishga tushirish
            document.querySelectorAll('.exam-sc-date').forEach(function(inp) {
                var sc = new ScrollCalendar(inp.id);
                // Agar saqlangan sana mavjud bo'lsa (canEdit rejimida) — qiymatni o'rnatish
                var initialVal = inp.getAttribute('data-initial-value');
                if (initialVal) {
                    sc.setValue(initialVal);
                }
            });

            // Sort funksiyasi
            initTableSort();

            // Form submit da validatsiya
            $('form').on('submit', function(e) {
                var hasError = false;
                // Faqat tahrirlash mumkin bo'lgan inputlarni tekshirish (readonly emas)
                document.querySelectorAll('.date-input-masked:not([readonly])').forEach(function(inp) {
                    if (!validateDateInput(inp)) hasError = true;
                });
                if (hasError) {
                    e.preventDefault();
                    alert('Sana xatosi: format noto\'g\'ri yoki imtihon sanasi bugun/o\'tgan kun qo\'yilgan.\nImtihon sanasi kamida ertadan bo\'lishi kerak (kk.oo.yyyy).');
                }
            });
        });
    </script>

    <style>
        /* Test markazi yuklanganligi statistikasi */
        .tc-load-wrap { padding: 12px 20px; background: #fff; border-bottom: 1px solid #e2e8f0; }
        .tc-load-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        .tc-load-title { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #0f172a; }
        .tc-load-title .tc-load-sub { font-weight: 500; color: #64748b; font-size: 12px; }
        .tc-load-stats { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #475569; flex-wrap: wrap; }
        .tc-stat-item b { color: #0f172a; font-weight: 700; }
        .tc-stat-item.tc-stat-busy b { color: #b45309; }
        .tc-stat-item.tc-stat-free b { color: #15803d; }
        .tc-stat-sep { color: #cbd5e1; }
        .tc-load-strip { display: flex; gap: 4px; overflow-x: auto; padding: 4px 2px 8px; scrollbar-width: thin; }
        .tc-load-strip::-webkit-scrollbar { height: 6px; }
        .tc-load-strip::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .tc-day { flex: 0 0 auto; min-width: 56px; padding: 6px 4px; border: 1px solid; border-radius: 8px; text-align: center; cursor: default; transition: transform 0.12s, box-shadow 0.12s; }
        .tc-day:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .tc-day.tc-weekend { opacity: 0.7; }
        .tc-day-date { font-size: 12px; font-weight: 700; color: #0f172a; line-height: 1.2; }
        .tc-day-wd { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 1px; }
        .tc-day-total { font-size: 17px; font-weight: 800; color: #0f172a; margin-top: 4px; line-height: 1; }
        .tc-day.tc-high .tc-day-total { color: #b91c1c; }
        .tc-day.tc-mid .tc-day-total { color: #c2410c; }
        .tc-day.tc-low .tc-day-total { color: #a16207; }
        .tc-day-total.tc-day-empty { color: #cbd5e1; font-weight: 600; font-size: 14px; margin-top: 6px; }
        .tc-day-detail { display: flex; gap: 2px; justify-content: center; margin-top: 3px; flex-wrap: wrap; }
        .tc-day-detail.tc-day-empty-label { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .tc-pill { display: inline-block; padding: 1px 4px; border-radius: 4px; font-size: 9px; font-weight: 700; line-height: 1.3; }
        .tc-pill-o { background: rgba(29,78,216,0.12); color: #1d4ed8; }
        .tc-pill-t { background: rgba(21,128,61,0.12); color: #15803d; }
        .tc-load-legend { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px; font-size: 11px; color: #64748b; }
        .tc-leg { display: inline-flex; align-items: center; gap: 4px; }
        .tc-leg-sw { width: 12px; height: 12px; border-radius: 3px; border: 1px solid; display: inline-block; }

        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; overflow: visible; position: relative; z-index: 20; }
        .filter-row { display: flex; gap: 10px; flex-wrap: nowrap; margin-bottom: 10px; align-items: flex-end; overflow: visible; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .filter-item { flex: 0 0 auto; }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        .toggle-switch { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
        .toggle-track { width: 40px; height: 22px; background: #cbd5e1; border-radius: 11px; position: relative; transition: background 0.25s; flex-shrink: 0; }
        .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
        .toggle-thumb { width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
        .toggle-switch.active .toggle-thumb { transform: translateX(18px); }
        .toggle-label { font-size: 12px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .toggle-switch.active .toggle-label { color: #1e3a5f; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }
        .btn-save { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-save:hover { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }

        .btn-excel-export { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #047857, #10b981); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(4,120,87,0.3); height: 36px; text-decoration: none; }
        .btn-excel-export:hover { background: linear-gradient(135deg, #065f46, #047857); box-shadow: 0 4px 12px rgba(4,120,87,0.4); transform: translateY(-1px); color: #fff; }

        .debt-badge { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 10px; font-size: 9px; font-weight: 700; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; cursor: help; white-space: pre-line; }
        .debt-badge:hover { background: #fde68a; border-color: #f59e0b; }

        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .schedule-table thead { position: sticky; top: 0; z-index: 10; }
        .schedule-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .schedule-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .schedule-table th.sortable { cursor: pointer; user-select: none; transition: background 0.15s; }
        .schedule-table th.sortable:hover { background: rgba(43,94,167,0.1); }
        .sort-icon { font-size: 10px; color: #2b5ea7; }
        .data-row td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-row:hover td { background: #f8fafc; }
        .data-row .sc-wrap { min-width: 140px; }
        .data-row .sc-dropdown { z-index: 9999; }

        .lesson-date-badge { display: inline-flex; padding: 4px 8px; font-size: 12px; font-weight: 600; border-radius: 6px; line-height: 1.3; background: #f0f9ff; color: #0369a1; }
        .date-input-masked { height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px; font-size: 13px; font-weight: 500; color: #1e293b; background: #fff; width: 100%; min-width: 110px; outline: none; transition: border-color 0.2s; text-align: center; letter-spacing: 0.5px; }
        .date-input-masked:hover { border-color: #2b5ea7; }
        .date-input-masked:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .date-input-masked::placeholder { color: #94a3b8; font-weight: 400; letter-spacing: 0; }
        .date-input-masked.date-error { border-color: #ef4444; background: #fef2f2; }
        .date-input-masked.date-error:focus { box-shadow: 0 0 0 2px rgba(239,68,68,0.2); }
        .exam-cell { display: flex; align-items: center; gap: 6px; justify-content: center; }
        .exam-date-wrap { flex: 1; min-width: 0; }
        .na-toggle { display: inline-flex; align-items: center; gap: 3px; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
        .na-toggle input[type="checkbox"] { width: 14px; height: 14px; accent-color: #ef4444; cursor: pointer; margin: 0; }
        .na-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.03em; }
        .na-toggle input:checked + .na-label { color: #ef4444; }
        .date-input-locked { background: #f1f5f9 !important; color: #64748b !important; cursor: not-allowed !important; border-color: #e2e8f0 !important; }
        .date-input-locked:hover { border-color: #e2e8f0 !important; box-shadow: none !important; }
        .na-toggle-locked { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        .lock-icon { font-size: 12px; flex-shrink: 0; opacity: 0.6; }
        .clear-date-btn { background: none; border: none; cursor: pointer; padding: 2px 3px; color: #ef4444; opacity: 0.7; line-height: 1; border-radius: 4px; flex-shrink: 0; }
        .clear-date-btn:hover { opacity: 1; background: #fee2e2; }
        .cf-chip { display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 6px; font-size: 10.5px; font-weight: 600; line-height: 1.4; white-space: nowrap; vertical-align: middle; letter-spacing: 0.01em; }
        .cf-chip-cell { margin-left: 0; padding: 3px 10px; font-size: 11px; }
    </style>
</x-app-layout>
