<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Yakuniy nazoratlar jadvali
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">

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
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> YN sanasi (dan)</label>
                            <input type="text" id="date_from" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> YN sanasi (gacha)</label>
                            <input type="text" id="date_to" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch {{ ($currentSemesterToggle ?? '1') === '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
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
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <button type="button" class="btn-refresh" id="btn-refresh-quiz" onclick="refreshQuizCounts()">
                                    <svg class="refresh-icon" style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span id="refresh-label">Yangilash</span>
                                </button>
                                <button type="button" class="btn-calc" onclick="applyFilter()">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    Qidirish
                                </button>
                                <button type="button" id="btn-yn-oldi-word" class="btn-yn-oldi-word" onclick="tcGenerateYnOldiWord()" disabled>
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    YN oldi word
                                </button>
                                <button type="button" class="btn-export-excel" onclick="tcExportExcel()">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    $tcDefaults = \App\Services\ExamCapacityService::getSettings();
                    $tcReadOnly = $readOnly ?? false;
                @endphp
                @if(!$tcReadOnly)
                <!-- Inline day override panel -->
                <div id="day-override-panel" data-defaults='@json($tcDefaults)' style="margin:0 16px 14px 16px;background:linear-gradient(135deg,#f0fdfa,#ccfbf1);border:1px solid #5eead4;border-radius:12px;padding:12px 14px;">
                    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;">
                        <div style="display:flex;align-items:center;gap:8px;min-width:170px;">
                            <svg style="width:18px;height:18px;color:#0f766e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#115e59;">Kun sozlamasi</div>
                                <div style="font-size:11px;color:#0f766e;">Tanlangan kunlar uchun</div>
                            </div>
                        </div>

                        <div style="flex:1;min-width:240px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#0f766e;margin-bottom:4px;">Sanalar</label>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <input type="date" id="do-date-input" style="padding:6px 10px;border:1px solid #5eead4;border-radius:6px;font-size:13px;background:#fff;">
                                <button type="button" onclick="tcAddDate()" style="padding:6px 10px;background:#0d9488;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">+ Qo'shish</button>
                            </div>
                            <div id="do-dates-chips" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;min-height:22px;"></div>
                        </div>

                        <div style="min-width:115px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#0f766e;margin-bottom:4px;">Ish boshl.</label>
                            <input type="time" id="do-work-start" style="padding:6px 8px;border:1px solid #5eead4;border-radius:6px;font-size:13px;width:100%;background:#fff;">
                        </div>
                        <div style="min-width:115px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#0f766e;margin-bottom:4px;">Ish tug.</label>
                            <input type="time" id="do-work-end" style="padding:6px 8px;border:1px solid #5eead4;border-radius:6px;font-size:13px;width:100%;background:#fff;">
                        </div>
                        <div style="min-width:115px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#92400e;margin-bottom:4px;">Tushlik boshl.</label>
                            <input type="time" id="do-lunch-start" style="padding:6px 8px;border:1px solid #fbbf24;border-radius:6px;font-size:13px;width:100%;background:#fffbeb;">
                        </div>
                        <div style="min-width:115px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#92400e;margin-bottom:4px;">Tushlik tug.</label>
                            <input type="time" id="do-lunch-end" style="padding:6px 8px;border:1px solid #fbbf24;border-radius:6px;font-size:13px;width:100%;background:#fffbeb;">
                        </div>
                        <div style="min-width:90px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#0f766e;margin-bottom:4px;">Kompyuter</label>
                            <input type="number" id="do-computers" min="1" style="padding:6px 8px;border:1px solid #5eead4;border-radius:6px;font-size:13px;width:100%;background:#fff;">
                        </div>
                        <div style="min-width:95px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#0f766e;margin-bottom:4px;">Davomiyligi</label>
                            <input type="number" id="do-duration" min="1" style="padding:6px 8px;border:1px solid #5eead4;border-radius:6px;font-size:13px;width:100%;background:#fff;">
                        </div>
                        <div style="flex:1;min-width:160px;">
                            <label style="display:block;font-size:11px;font-weight:600;color:#0f766e;margin-bottom:4px;">Izoh</label>
                            <input type="text" id="do-note" maxlength="255" placeholder="ixtiyoriy" style="padding:6px 10px;border:1px solid #5eead4;border-radius:6px;font-size:13px;width:100%;background:#fff;">
                        </div>

                        <div style="display:flex;gap:6px;align-items:flex-end;">
                            <button type="button" onclick="tcResetOverrideForm()" title="Default qiymatlarga qaytarish" style="padding:6px 10px;background:#fff;color:#475569;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;font-size:12px;">↺ Reset</button>
                            <button type="button" onclick="tcSaveDayOverride(true)" title="Tanlangan kunlardagi maxsus sozlamani o'chirish" style="padding:6px 10px;background:#fff;color:#e11d48;border:1px solid #fda4af;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;">Tozalash</button>
                            <button type="button" onclick="tcSaveDayOverride(false)" style="padding:6px 14px;background:linear-gradient(135deg,#14b8a6,#0d9488);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;">Saqlash</button>
                        </div>
                    </div>
                    <div id="do-status" style="margin-top:8px;font-size:12px;color:#64748b;min-height:16px;"></div>
                </div>
                @endif

                <!-- Results -->
                @if($scheduleData->count() > 0)
                <div>
                    <div style="overflow-x:auto;">
                        <table class="schedule-table">
                            <thead>
                                <tr class="header-row">
                                    <th style="width:40px;text-align:center;">
                                        <input type="checkbox" id="tc-select-all-header" onchange="tcToggleSelectAll(this)" style="accent-color:#2b5ea7;width:16px;height:16px;cursor:pointer;">
                                    </th>
                                    <th style="width:44px;padding-left:16px;">#</th>
                                    <th class="sortable" data-col="2">Guruh <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="3">Yo'nalish <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="4" style="width:100px;">Fan kodi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="5">Fan nomi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="6" style="width:70px;text-align:center;">Kurs <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="7" style="width:90px;text-align:center;">Semestr <span class="sort-icon"></span></th>
                                    <th style="width:100px;text-align:center;">Urinish</th>
                                    <th class="sortable" data-col="9" style="width:100px;text-align:center;">YN turi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="10" style="width:140px;text-align:center;">Sana <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="11" style="width:100px;text-align:center;">Topshirgan <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="12" style="width:120px;text-align:center;">YN yuborilgan <span class="sort-icon"></span></th>
                                    <th style="width:160px;text-align:center;">Test vaqti</th>
                                </tr>
                                <tr class="filter-header-row">
                                    <th></th>
                                    <th></th>
                                    <th><select class="col-filter" data-col="2"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="3"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="4"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="5"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="6"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="7"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="8"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="9"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="10"><option value="">Barchasi</option></select></th>
                                    <th>
                                        <select class="col-filter color-filter" data-col="11" data-filter-type="color">
                                            <option value="">Barchasi</option>
                                            <option value="green" data-color="#16a34a">Yashil</option>
                                            <option value="yellow" data-color="#d97706">Sariq</option>
                                            <option value="red" data-color="#dc2626">Qizil</option>
                                        </select>
                                    </th>
                                    <th>
                                        <select class="col-filter color-filter" data-col="12" data-filter-type="color">
                                            <option value="">Barchasi</option>
                                            <option value="green" data-color="#16a34a">Yuborilgan</option>
                                            <option value="red" data-color="#dc2626">Yuborilmagan</option>
                                        </select>
                                    </th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="schedule-tbody">
                                @php
                                    $rowIndex = 0;
                                    $today = now()->format('Y-m-d');
                                @endphp
                                @foreach($scheduleData as $groupHemisId => $items)
                                    @foreach($items as $item)
                                        <tr class="data-row" data-group-id="{{ $item['group']->group_hemis_id }}" data-subject-id="{{ $item['subject']->subject_id ?? '' }}" data-yn-type="{{ $item['yn_type'] ?? '' }}" data-semester-code="{{ $item['subject']->semester_code ?? '' }}">
                                            <td style="text-align:center;">
                                                <input type="checkbox" class="tc-row-checkbox" data-group-hemis-id="{{ $item['group']->group_hemis_id }}" data-semester-code="{{ $item['subject']->semester_code ?? '' }}" data-subject-id="{{ $item['subject']->subject_id ?? '' }}" onchange="tcUpdateSelection()" style="accent-color:#2b5ea7;width:16px;height:16px;cursor:pointer;">
                                            </td>
                                            <td class="row-num" style="color:#94a3b8;font-weight:500;padding-left:16px;">{{ ++$rowIndex }}</td>
                                            <td data-sort-value="{{ $item['group']->name }}" style="font-weight:600;color:#0f172a;">{{ $item['group']->name }}</td>
                                            <td data-sort-value="{{ $item['specialty_name'] }}" style="color:#64748b;font-size:12px;">{{ $item['specialty_name'] }}</td>
                                            <td data-sort-value="{{ $item['subject_code'] }}" style="color:#64748b;font-size:12px;">{{ $item['subject_code'] }}</td>
                                            <td data-sort-value="{{ $item['subject']->subject_name }}" style="font-weight:500;color:#1e293b;">{{ $item['subject']->subject_name }}</td>
                                            <td data-sort-value="{{ $item['level_name'] }}" style="text-align:center;color:#1e293b;font-weight:500;">{{ $item['level_name'] }}</td>
                                            <td data-sort-value="{{ $item['semester_name'] }}" style="text-align:center;color:#64748b;font-size:12px;">{{ $item['semester_name'] }}</td>
                                            <td data-sort-value="1-urinish" style="text-align:center;padding:4px 8px;">
                                                <span class="attempt-badge">1-urinish</span>
                                                @if(($item['excuse_student_count'] ?? 0) > 0)
                                                    <br><span style="display:inline-block;margin-top:2px;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;" title="Sababli talabalar soni">qo'shimcha: +{{ $item['excuse_student_count'] }}</span>
                                                @endif
                                            </td>
                                            <td data-sort-value="{{ $item['yn_type'] ?? '' }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['yn_type'] ?? null)
                                                    <span class="yn-type-badge yn-type-{{ strtolower($item['yn_type']) }}">{{ $item['yn_type'] }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td data-sort-value="{{ ($item['yn_na'] ?? false) ? '' : (($item['yn_date'] ?? null) ? \Carbon\Carbon::parse($item['yn_date'])->format('d.m.Y') : '') }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['yn_na'] ?? false)
                                                    <span class="na-badge">N/A</span>
                                                @elseif($item['yn_date'] ?? null)
                                                    @php
                                                        $ynDate = $item['yn_date_carbon'] ?? null;
                                                        $badgeClass = (($item['yn_type'] ?? '') === 'Test') ? 'badge-pending-blue' : 'badge-pending';
                                                        if ($ynDate && $ynDate->format('Y-m-d') === $today) $badgeClass = 'badge-today';
                                                        elseif ($ynDate && $ynDate->isPast()) $badgeClass = 'badge-passed';
                                                        elseif ($ynDate && $ynDate->diffInDays(now()) <= 3) $badgeClass = 'badge-soon';
                                                    @endphp
                                                    <span class="date-badge {{ $badgeClass }}">{{ $ynDate?->format('d.m.Y') }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            @php
                                                $sc = $item['student_count'] ?? 0;
                                                $qc = $item['quiz_count'] ?? 0;
                                                $qcClass = $qc == 0 ? 'quiz-count-zero' : ($qc >= $sc ? 'quiz-count-full' : 'quiz-count-partial');
                                                $qcColor = $qc == 0 ? 'red' : ($qc >= $sc ? 'green' : 'yellow');
                                            @endphp
                                            <td class="td-quiz-count" data-sort-value="{{ $qc }}" data-color="{{ $qcColor }}" style="text-align:center;">
                                                <span class="{{ $qcClass }}">{{ $qc }}/{{ $sc }}</span>
                                            </td>
                                            <td data-sort-value="{{ ($item['yn_submitted'] ?? false) ? 'Yuborilgan' : 'Yuborilmagan' }}" data-color="{{ ($item['yn_submitted'] ?? false) ? 'green' : 'red' }}" style="text-align:center;">
                                                @if($item['yn_submitted'] ?? false)
                                                    <span class="yn-submitted-yes">Yuborilgan</span>
                                                @else
                                                    <span class="yn-submitted-no">Yuborilmagan</span>
                                                @endif
                                            </td>
                                            <td style="text-align:center;padding:4px 6px;">
                                                    <div style="display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:wrap;">
                                                        <input type="text" class="test-time-input" value="{{ $item['test_time'] ? \Carbon\Carbon::parse($item['test_time'])->format('H:i') : '' }}" data-group-hemis-id="{{ $item['group']->group_hemis_id }}" data-subject-id="{{ $item['subject']->subject_id ?? '' }}" data-semester-code="{{ $item['subject']->semester_code ?? '' }}" data-subject-name="{{ $item['subject']->subject_name ?? '' }}" data-yn-type="{{ $item['yn_type'] ?? '' }}" data-yn-submitted="{{ ($item['yn_submitted'] ?? false) ? '1' : '0' }}" placeholder="HH:MM" maxlength="5" style="width:90px;padding:3px 6px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;text-align:center;cursor:{{ $tcReadOnly ? 'default' : 'pointer' }};{{ $tcReadOnly ? 'background:#f1f5f9;color:#475569;' : '' }}" {{ $tcReadOnly ? 'readonly' : '' }} @if(!$tcReadOnly) oninput="formatTimeInput(this)" onblur="validateTimeInput(this)" @endif>
                                                        @if(!$tcReadOnly)
                                                        <button type="button" class="save-test-time-btn" onclick="saveTestTime(this)" style="padding:3px 8px;background:#3b82f6;color:#fff;border:none;border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap;" title="Saqlash">
                                                            <svg style="width:14px;height:14px;display:inline-block;vertical-align:middle;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                        </button>
                                                        @endif
                                                        @if(!($item['yn_submitted'] ?? false) && $item['test_time'])
                                                            <div class="yn-time-note" style="width:100%;text-align:center;margin-top:2px;">
                                                                <span style="font-size:10px;color:#d97706;font-style:italic;">⚠️ Vaqt o'zgarishi mumkin</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @elseif($isSearched)
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Jadval topilmadi</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Tanlangan filtrlar bo'yicha imtihon sanalari topilmadi.</p>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <style>
        .toast-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            padding: 18px 24px;
            min-width: 320px;
            max-width: 420px;
            animation: toastSlideIn 0.3s ease-out;
            border-left: 4px solid #16a34a;
        }
        .toast-popup.toast-error {
            border-left-color: #dc2626;
        }
        .toast-popup .toast-title {
            font-size: 15px;
            font-weight: 700;
            color: #16a34a;
            margin-bottom: 6px;
        }
        .toast-popup.toast-error .toast-title {
            color: #dc2626;
        }
        .toast-popup .toast-body {
            font-size: 13px;
            color: #334155;
            line-height: 1.5;
        }
        .toast-popup .toast-close {
            position: absolute;
            top: 8px;
            right: 12px;
            background: none;
            border: none;
            font-size: 18px;
            color: #94a3b8;
            cursor: pointer;
        }
        @keyframes toastSlideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastSlideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    </style>

    <script>
        function showToast(title, body, isError) {
            var existing = document.querySelector('.toast-popup');
            if (existing) existing.remove();

            var toast = document.createElement('div');
            toast.className = 'toast-popup' + (isError ? ' toast-error' : '');
            toast.innerHTML = '<button class="toast-close" onclick="this.parentElement.remove()">&times;</button>'
                + '<div class="toast-title">' + title + '</div>'
                + '<div class="toast-body">' + body + '</div>';
            document.body.appendChild(toast);

            setTimeout(function() {
                toast.style.animation = 'toastSlideOut 0.3s ease-in forwards';
                setTimeout(function() { toast.remove(); }, 300);
            }, 4000);
        }

        function formatTimeInput(input) {
            var val = input.value.replace(/[^0-9]/g, '');
            if (val.length > 4) val = val.substring(0, 4);
            if (val.length >= 3) {
                val = val.substring(0, 2) + ':' + val.substring(2);
            }
            input.value = val;
        }

        function validateTimeInput(input) {
            var val = input.value.trim();
            if (!val) return;
            var match = val.match(/^(\d{1,2}):(\d{2})$/);
            if (!match) {
                input.value = '';
                showToast('Xatolik', 'Vaqt formati noto\'g\'ri. HH:MM formatida kiriting (masalan: 09:00, 14:30)', true);
                return;
            }
            var h = parseInt(match[1], 10);
            var m = parseInt(match[2], 10);
            if (h > 23 || m > 59) {
                input.value = '';
                showToast('Xatolik', 'Vaqt noto\'g\'ri. Soat 0-23, daqiqa 0-59 oralig\'ida bo\'lishi kerak', true);
                return;
            }
            input.value = (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m);
        }

        function saveTestTime(btn, force) {
            var container = btn.parentElement;
            var input = container.querySelector('.test-time-input');
            var timeVal = input.value.trim();
            if (!timeVal) {
                showToast('Xatolik', 'Iltimos, vaqtni kiriting', true);
                return;
            }

            var match = timeVal.match(/^(\d{1,2}):(\d{2})$/);
            if (!match || parseInt(match[1]) > 23 || parseInt(match[2]) > 59) {
                showToast('Xatolik', 'Vaqt formati noto\'g\'ri. HH:MM formatida kiriting', true);
                return;
            }

            var subjectName = input.getAttribute('data-subject-name') || 'Fan';

            btn.disabled = true;
            btn.style.opacity = '0.6';

            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch('{{ route($routePrefix . ".academic-schedule.test-center.save-test-time") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    group_hemis_id: input.getAttribute('data-group-hemis-id'),
                    subject_id: input.getAttribute('data-subject-id'),
                    semester_code: input.getAttribute('data-semester-code'),
                    yn_type: input.getAttribute('data-yn-type') || 'Test',
                    test_time: timeVal,
                    yn_submitted: input.getAttribute('data-yn-submitted') === '1',
                    force: force === true
                })
            })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data.success) {
                    var isChanged = data.time_changed;
                    var title = isChanged ? 'O\'zgartirildi!' : 'Saqlandi!';
                    var body = '<b>Fan:</b> ' + subjectName + '<br><b>Test vaqti ' + (isChanged ? 'o\'zgartirildi' : 'belgilandi') + ':</b> ' + timeVal;
                    showToast(title, body, false);
                    btn.style.background = '#16a34a';
                    setTimeout(function() { btn.style.background = '#3b82f6'; }, 1500);

                    // YN yuborilmagan bo'lsa izoh qo'shish/yangilash
                    var wrapper = input.closest('div');
                    var existingNote = wrapper.querySelector('.yn-time-note');
                    if (input.getAttribute('data-yn-submitted') !== '1' && timeVal) {
                        if (!existingNote) {
                            var noteDiv = document.createElement('div');
                            noteDiv.style.cssText = 'width:100%;text-align:center;margin-top:2px;';
                            noteDiv.className = 'yn-time-note';
                            noteDiv.innerHTML = '<span style="font-size:10px;color:#d97706;font-style:italic;">⚠️ Vaqt o\'zgarishi mumkin</span>';
                            wrapper.appendChild(noteDiv);
                        }
                    } else if (existingNote) {
                        existingNote.remove();
                    }
                } else if (data.error_code === 'lesson_conflict') {
                    input.value = '';
                    showLessonConflictModal(data, subjectName, timeVal, btn, input);
                } else {
                    showToast('Xatolik', data.message || 'Xatolik yuz berdi', true);
                }
            })
            .catch(function(err) {
                if (err && err.error_code === 'lesson_conflict') {
                    input.value = '';
                    showLessonConflictModal(err, subjectName, timeVal, btn, input);
                } else {
                    showToast('Xatolik', 'Xatolik yuz berdi', true);
                }
            })
            .finally(function() {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        }

        // Dars to'qnashuvi modali — shu guruhning shu sanada/vaqtda darslari mavjud
        function showLessonConflictModal(data, subjectName, timeVal, btn, input) {
            closeLessonConflictModal();
            window.__lessonConflictPending = { btn: btn, input: input, timeVal: timeVal };
            var lessons = Array.isArray(data.lessons) ? data.lessons : [];
            var rowsHtml = '';
            lessons.forEach(function(l, i) {
                rowsHtml += '<tr>'
                    + '<td style="padding:8px 10px;color:#475569;font-size:12px;">' + (i + 1) + '</td>'
                    + '<td style="padding:8px 10px;font-weight:600;color:#0f172a;">' + (l.subject_name || '-') + '</td>'
                    + '<td style="padding:8px 10px;color:#64748b;font-size:12px;">' + (l.training_type || '-') + '</td>'
                    + '<td style="padding:8px 10px;color:#1e293b;font-weight:600;text-align:center;">' + (l.start || '') + ' – ' + (l.end || '') + '</td>'
                    + '<td style="padding:8px 10px;color:#475569;font-size:12px;">' + (l.pair_name || '-') + '</td>'
                    + '</tr>';
            });
            var html = ''
                + '<div id="lesson-conflict-overlay" style="position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;">'
                + '<div style="background:#fff;border-radius:14px;max-width:680px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;">'
                + '<div style="padding:14px 22px;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;display:flex;align-items:center;justify-content:space-between;">'
                + '<h3 style="margin:0;font-size:16px;font-weight:700;">⚠️ Vaqt ustma-ust tushdi — guruh darsda</h3>'
                + '<button type="button" onclick="closeLessonConflictModal()" style="background:none;border:none;color:#fff;font-size:24px;cursor:pointer;line-height:1;padding:0 6px;">&times;</button>'
                + '</div>'
                + '<div style="padding:18px 22px;overflow-y:auto;">'
                + '<p style="margin:0 0 12px;color:#475569;font-size:13px;">'
                + '<b>' + esc(subjectName) + '</b> uchun tanlangan <b>' + esc(data.date || '') + '</b> sanasida <b>' + esc(data.time_range || timeVal) + '</b> oralig\'ida bu guruhning quyidagi darslari mavjud:'
                + '</p>'
                + '<table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">'
                + '<thead><tr style="background:#f8fafc;">'
                + '<th style="padding:8px 10px;text-align:left;font-size:11px;color:#64748b;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">#</th>'
                + '<th style="padding:8px 10px;text-align:left;font-size:11px;color:#64748b;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Fan</th>'
                + '<th style="padding:8px 10px;text-align:left;font-size:11px;color:#64748b;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Tur</th>'
                + '<th style="padding:8px 10px;text-align:center;font-size:11px;color:#64748b;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Vaqt</th>'
                + '<th style="padding:8px 10px;text-align:left;font-size:11px;color:#64748b;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Juftlik</th>'
                + '</tr></thead>'
                + '<tbody>' + (rowsHtml || '<tr><td colspan="5" style="padding:14px;text-align:center;color:#94a3b8;">Ma\'lumot yo\'q</td></tr>') + '</tbody>'
                + '</table>'
                + '<div style="margin-top:14px;padding:10px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#991b1b;font-size:12px;font-weight:500;">'
                + 'Bu vaqt oralig\'ida YN belgilab bo\'lmaydi. Iltimos boshqa vaqt yoki sanani tanlang.'
                + '</div>'
                + '</div>'
                + '<div style="padding:12px 22px;border-top:1px solid #e5e7eb;background:#f8fafc;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">'
                + '<button type="button" onclick="closeLessonConflictModal()" style="padding:8px 22px;background:#fff;color:#475569;font-size:13px;font-weight:600;border:1px solid #cbd5e1;border-radius:8px;cursor:pointer;">Yopish</button>'
                + '<button type="button" onclick="forceSaveTestTime()" style="padding:8px 22px;background:linear-gradient(135deg,#ea580c,#f97316);color:#fff;font-size:13px;font-weight:700;border:none;border-radius:8px;cursor:pointer;box-shadow:0 4px 12px rgba(234,88,12,0.35);">Baribir saqlansin</button>'
                + '</div>'
                + '</div>'
                + '</div>';
            document.body.insertAdjacentHTML('beforeend', html);
        }
        function closeLessonConflictModal() {
            var el = document.getElementById('lesson-conflict-overlay');
            if (el) el.remove();
            window.__lessonConflictPending = null;
        }

        // "Baribir saqlash" — dars to'qnashishi e'tiborsiz qoldirilib, force=true bilan qayta jo'natiladi
        function forceSaveTestTime() {
            var pending = window.__lessonConflictPending;
            if (!pending || !pending.btn || !pending.input) {
                closeLessonConflictModal();
                return;
            }
            // Tozalangan inputga vaqtni qaytaramiz
            pending.input.value = pending.timeVal;
            closeLessonConflictModal();
            saveTestTime(pending.btn, true);
        }
        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
                return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
            });
        }
    </script>

    <script>
        var isUpdatingFilters = false;
        var filterUrl = '{{ route($routePrefix . ".academic-schedule.get-filter-options") }}';

        var initialValues = {
            education_type: '{{ $selectedEducationType ?? '' }}',
            department_id: '{{ $selectedDepartment ?? '' }}',
            specialty_id: '{{ $selectedSpecialty ?? '' }}',
            level_code: '{{ $selectedLevelCode ?? '' }}',
            semester_code: '{{ $selectedSemester ?? '' }}',
            group_id: '{{ $selectedGroup ?? '' }}',
            subject_id: '{{ $selectedSubject ?? '' }}'
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
        }

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

        function updateSelect(selector, items, valueKey, textKey) {
            var $el = $(selector);
            var currentVal = $el.val();
            $el.empty().append('<option value="">Barchasi</option>');
            $.each(items, function(i, item) {
                $el.append('<option value="' + item[valueKey] + '">' + item[textKey] + '</option>');
            });
            if (currentVal && $el.find('option[value="' + currentVal + '"]').length) {
                $el.val(currentVal);
            }
            $el.trigger('change.select2');
        }

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

        var refreshQuizUrl = '{{ route($routePrefix . ".academic-schedule.test-center.refresh-quiz-counts") }}';

        function refreshQuizCounts() {
            var rows = document.querySelectorAll('#schedule-tbody tr.data-row');
            if (!rows.length) return;

            var btn = document.getElementById('btn-refresh-quiz');
            var icon = btn.querySelector('.refresh-icon');
            var label = document.getElementById('refresh-label');
            btn.disabled = true;
            icon.classList.add('spinning');
            label.textContent = 'Yangilanmoqda...';

            // Collect unique group+subject+yn_type combinations
            var seen = {};
            var items = [];
            rows.forEach(function(row) {
                var gid = row.getAttribute('data-group-id');
                var sid = row.getAttribute('data-subject-id');
                var yn = row.getAttribute('data-yn-type');
                var key = gid + '|' + sid + '|' + yn;
                if (!seen[key]) {
                    seen[key] = true;
                    items.push({ group_id: gid, subject_id: sid, yn_type: yn });
                }
            });

            $.ajax({
                url: refreshQuizUrl,
                method: 'POST',
                data: JSON.stringify({ items: items }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                success: function(data) {
                    // Build lookup
                    var lookup = {};
                    (data.counts || []).forEach(function(c) {
                        lookup[c.group_id + '|' + c.subject_id + '|' + c.yn_type] = c;
                    });

                    // Update each row
                    rows.forEach(function(row) {
                        var key = row.getAttribute('data-group-id') + '|' + row.getAttribute('data-subject-id') + '|' + row.getAttribute('data-yn-type');
                        var info = lookup[key];
                        if (!info) return;

                        var qcCell = row.querySelector('.td-quiz-count');
                        if (qcCell) {
                            var cls = info.quiz_count == 0 ? 'quiz-count-zero' : (info.quiz_count >= info.student_count ? 'quiz-count-full' : 'quiz-count-partial');
                            var clr = info.quiz_count == 0 ? 'red' : (info.quiz_count >= info.student_count ? 'green' : 'yellow');
                            qcCell.innerHTML = '<span class="' + cls + '">' + info.quiz_count + '/' + info.student_count + '</span>';
                            qcCell.setAttribute('data-sort-value', info.quiz_count);
                            qcCell.setAttribute('data-color', clr);
                        }
                    });

                    label.textContent = 'Yangilash';
                    icon.classList.remove('spinning');
                    btn.disabled = false;
                },
                error: function() {
                    label.textContent = 'Yangilash';
                    icon.classList.remove('spinning');
                    btn.disabled = false;
                    alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                }
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
            url.searchParams.set('current_semester', cs);
            window.location.href = url.toString();
        }

        // ===== Inline day override panel =====
        var DAY_OVERRIDE_SAVE = '{{ route($routePrefix . ".academic-schedule.test-center.day-override.save") }}';
        var DO_SELECTED_DATES = [];
        var DO_DEFAULTS = (function(){
            var el = document.getElementById('day-override-panel');
            try { return JSON.parse(el.getAttribute('data-defaults') || '{}'); } catch(e){ return {}; }
        })();

        function tcResetOverrideForm() {
            document.getElementById('do-work-start').value = DO_DEFAULTS.work_hours_start || '';
            document.getElementById('do-work-end').value = DO_DEFAULTS.work_hours_end || '';
            document.getElementById('do-lunch-start').value = DO_DEFAULTS.lunch_start || '';
            document.getElementById('do-lunch-end').value = DO_DEFAULTS.lunch_end || '';
            document.getElementById('do-computers').value = DO_DEFAULTS.computer_count || '';
            document.getElementById('do-duration').value = DO_DEFAULTS.test_duration_minutes || '';
            document.getElementById('do-note').value = '';
        }

        function tcRenderDateChips() {
            var box = document.getElementById('do-dates-chips');
            if (!DO_SELECTED_DATES.length) {
                box.innerHTML = '<span style="font-size:11px;color:#94a3b8;font-style:italic;">Sana qo\'shilmagan</span>';
                return;
            }
            box.innerHTML = DO_SELECTED_DATES.map(function(d, i){
                var human = d.split('-').reverse().join('.');
                return '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#0d9488;color:#fff;border-radius:12px;font-size:11px;font-weight:600;">'
                    + human
                    + '<button type="button" onclick="tcRemoveDate(' + i + ')" style="background:none;border:none;color:#fff;cursor:pointer;font-size:14px;line-height:1;padding:0 2px;">&times;</button>'
                    + '</span>';
            }).join('');
        }

        function tcAddDate() {
            var input = document.getElementById('do-date-input');
            var d = input.value;
            if (!d) return;
            if (DO_SELECTED_DATES.indexOf(d) === -1) {
                DO_SELECTED_DATES.push(d);
                DO_SELECTED_DATES.sort();
                tcRenderDateChips();
            }
            input.value = '';
        }

        function tcRemoveDate(idx) {
            DO_SELECTED_DATES.splice(idx, 1);
            tcRenderDateChips();
        }

        function tcSaveDayOverride(clearAll) {
            var status = document.getElementById('do-status');
            if (!DO_SELECTED_DATES.length) {
                status.textContent = 'Avval kamida bitta sana qo\'shing.';
                status.style.color = '#dc2626';
                return;
            }
            var payload = { dates: DO_SELECTED_DATES };
            if (clearAll) {
                payload.clear = true;
            } else {
                payload.work_hours_start = document.getElementById('do-work-start').value || null;
                payload.work_hours_end = document.getElementById('do-work-end').value || null;
                payload.lunch_start = document.getElementById('do-lunch-start').value || null;
                payload.lunch_end = document.getElementById('do-lunch-end').value || null;
                payload.computer_count = document.getElementById('do-computers').value || null;
                payload.test_duration_minutes = document.getElementById('do-duration').value || null;
                payload.note = document.getElementById('do-note').value || null;
            }
            status.textContent = 'Saqlanmoqda...';
            status.style.color = '#64748b';
            fetch(DAY_OVERRIDE_SAVE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            }).then(function(r){ return r.json().then(function(j){ return {ok:r.ok,data:j}; }); })
              .then(function(res){
                if (res.ok && res.data.success) {
                    var msg = res.data.message || 'Saqlandi';
                    if (res.data.per_day) {
                        var lines = [];
                        Object.keys(res.data.per_day).forEach(function(d){
                            var p = res.data.per_day[d];
                            var human = d.split('-').reverse().join('.');
                            lines.push(human + ': ish ' + p.effective.work_hours_start + '–' + p.effective.work_hours_end
                                + (p.effective.lunch_start ? ', tushlik ' + p.effective.lunch_start + '–' + p.effective.lunch_end : '')
                                + ', sig\'im ' + p.daily_capacity);
                        });
                        msg += ' (' + lines.join(' | ') + ')';
                    }
                    status.textContent = msg;
                    status.style.color = '#16a34a';
                } else {
                    status.textContent = (res.data && res.data.message) ? res.data.message : 'Xatolik yuz berdi';
                    status.style.color = '#dc2626';
                }
              }).catch(function(){
                status.textContent = 'Tarmoq xatosi.';
                status.style.color = '#dc2626';
              });
        }

        // Sahifa yuklanganda default qiymatlarni va bo'sh sanalar ro'yxatini ko'rsatish
        document.addEventListener('DOMContentLoaded', function(){
            tcResetOverrideForm();
            tcRenderDateChips();
            // Filterdagi dateFrom ni boshlang'ich sana sifatida qo'yish
            try {
                var df = $('#date_from').val();
                if (df) {
                    var p = df.split('.');
                    if (p.length === 3) document.getElementById('do-date-input').value = p[2] + '-' + p[1] + '-' + p[0];
                }
            } catch(e){}
        });

        function tcExportExcel() {
            var url = new URL('{{ route($routePrefix . ".academic-schedule.test-center.export-excel") }}');
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
            url.searchParams.set('current_semester', cs);
            window.location.href = url.toString();
        }

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            $('#education_type, #department_id, #specialty_id, #level_code, #semester_code').on('change', function() {
                if (!isUpdatingFilters) loadAllFilters();
            });

            initFilters();

            // Scroll calendar for date filters
            var calFrom = new ScrollCalendar('date_from');
            var calTo = new ScrollCalendar('date_to');
            @if($dateFrom)
                calFrom.setValue('{{ $dateFrom }}');
            @endif
            @if($dateTo)
                calTo.setValue('{{ $dateTo }}');
            @endif

            // Sort funksiyasi
            initTableSort();

            // Ustun filtrlari
            populateColumnFilters();
            document.querySelectorAll('.col-filter').forEach(function(sel) {
                sel.addEventListener('change', function() { applyColumnFilters(); });
            });
        });

        // Ustun filtrlarini to'ldirish
        function populateColumnFilters() {
            document.querySelectorAll('.col-filter').forEach(function(sel) {
                if (sel.getAttribute('data-filter-type') === 'color') return;
                var col = parseInt(sel.getAttribute('data-col'));
                var values = {};
                document.querySelectorAll('#schedule-tbody tr.data-row').forEach(function(row) {
                    var cell = row.cells[col];
                    if (!cell) return;
                    var val = (cell.getAttribute('data-sort-value') || cell.textContent || '').trim();
                    if (val && val !== '\u2014') values[val] = true;
                });
                var sorted = Object.keys(values).sort(function(a, b) { return a.localeCompare(b, 'uz'); });
                sel.innerHTML = '<option value="">Barchasi</option>';
                sorted.forEach(function(v) {
                    var opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    sel.appendChild(opt);
                });
            });
        }

        function applyColumnFilters() {
            var filters = {};
            var colorFilters = {};
            document.querySelectorAll('.col-filter').forEach(function(sel) {
                var col = parseInt(sel.getAttribute('data-col'));
                var val = sel.value;
                if (!val) return;
                if (sel.getAttribute('data-filter-type') === 'color') {
                    colorFilters[col] = val;
                } else {
                    filters[col] = val;
                }
            });
            var idx = 0;
            document.querySelectorAll('#schedule-tbody tr.data-row').forEach(function(row) {
                var show = true;
                for (var col in filters) {
                    var cell = row.cells[parseInt(col)];
                    if (!cell) { show = false; break; }
                    var cellVal = (cell.getAttribute('data-sort-value') || cell.textContent || '').trim();
                    if (cellVal !== filters[col]) { show = false; break; }
                }
                if (show) {
                    for (var col in colorFilters) {
                        var cell = row.cells[parseInt(col)];
                        if (!cell) { show = false; break; }
                        var cellColor = cell.getAttribute('data-color') || '';
                        if (cellColor !== colorFilters[col]) { show = false; break; }
                    }
                }
                row.style.display = show ? '' : 'none';
                if (show) {
                    idx++;
                    var numCell = row.querySelector('.row-num');
                    if (numCell) numCell.textContent = idx;
                }
            });
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
                if (/^\d+(\.\d+)?$/.test(aVal) && /^\d+(\.\d+)?$/.test(bVal)) {
                    return dir === 'asc' ? parseFloat(aVal) - parseFloat(bVal) : parseFloat(bVal) - parseFloat(aVal);
                }
                var dateRe = /^(\d{2})\.(\d{2})\.(\d{4})$/;
                var aM = aVal.match(dateRe);
                var bM = bVal.match(dateRe);
                if (aM && bM) {
                    var aD = aM[3] + aM[2] + aM[1];
                    var bD = bM[3] + bM[2] + bM[1];
                    return dir === 'asc' ? aD.localeCompare(bD) : bD.localeCompare(aD);
                }
                if (aM && !bM) return dir === 'asc' ? -1 : 1;
                if (!aM && bM) return dir === 'asc' ? 1 : -1;
                var cmp = aVal.localeCompare(bVal, 'uz');
                return dir === 'asc' ? cmp : -cmp;
            });
            rows.forEach(function(row, i) {
                tbody.appendChild(row);
                var numCell = row.querySelector('.row-num');
                if (numCell) numCell.textContent = i + 1;
            });
        }

        // Checkbox boshqaruvi
        function getVisibleCheckboxes() {
            var result = [];
            document.querySelectorAll('.tc-row-checkbox').forEach(function(cb) {
                var row = cb.closest('tr');
                if (row && row.style.display !== 'none') {
                    result.push(cb);
                }
            });
            return result;
        }

        function tcToggleSelectAll(el) {
            var checked = el.checked;
            getVisibleCheckboxes().forEach(function(cb) {
                cb.checked = checked;
            });
            tcUpdateSelection();
        }

        function tcUpdateSelection() {
            var visible = getVisibleCheckboxes();
            var checkedCount = visible.filter(function(cb) { return cb.checked; }).length;
            var btn = document.getElementById('btn-yn-oldi-word');
            if (btn) btn.disabled = checkedCount === 0;
            var headerCb = document.getElementById('tc-select-all-header');
            if (headerCb) headerCb.checked = checkedCount > 0 && checkedCount === visible.length;
        }

        var ynOldiWordUrl = '{{ route($routePrefix . ".academic-schedule.test-center.generate-yn-oldi-word") }}';

        function tcGenerateYnOldiWord() {
            var selected = [];
            document.querySelectorAll('.tc-row-checkbox:checked').forEach(function(cb) {
                selected.push({
                    group_hemis_id: cb.getAttribute('data-group-hemis-id'),
                    semester_code: String(cb.getAttribute('data-semester-code')),
                    subject_id: cb.getAttribute('data-subject-id')
                });
            });

            if (selected.length === 0) {
                alert('Kamida bitta guruhni tanlang');
                return;
            }

            console.log('YN oldi word - URL:', ynOldiWordUrl);
            console.log('YN oldi word - Data:', JSON.stringify(selected, null, 2));

            var btn = document.getElementById('btn-yn-oldi-word');
            var originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML =
                '<svg class="animate-spin" style="height:14px;width:14px;display:inline-block;margin-right:4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg> Yuklanmoqda...';

            fetch(ynOldiWordUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({ items: selected })
            })
            .then(function(response) {
                console.log('YN oldi word - Status:', response.status, 'Content-Type:', response.headers.get('content-type'));
                if (!response.ok) {
                    return response.text().then(function(text) {
                        console.error('YN oldi word - Error response:', text.substring(0, 500));
                        try {
                            var json = JSON.parse(text);
                            throw new Error(json.error || json.message || 'Xatolik yuz berdi');
                        } catch(e) {
                            if (e instanceof SyntaxError) {
                                throw new Error('Server xatoligi: ' + response.status + ' (console da batafsil)');
                            }
                            throw e;
                        }
                    });
                }
                var contentType = response.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    return response.json().then(function(json) {
                        throw new Error(json.error || json.message || 'Kutilmagan javob');
                    });
                }
                var disposition = response.headers.get('Content-Disposition');
                var filename = 'yn_oldi_qaydnoma.docx';
                if (disposition) {
                    var match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                    if (match && match[1]) filename = match[1].replace(/['"]/g, '');
                }
                return response.blob().then(function(blob) { return { blob: blob, filename: filename }; });
            })
            .then(function(data) {
                if (!data || !data.blob) return;
                var url = window.URL.createObjectURL(data.blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
            })
            .catch(function(err) {
                console.error('YN oldi word - Catch:', err);
                alert(err.message);
            })
            .finally(function() {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        }
    </script>

    <style>
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

        .btn-refresh { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; background: linear-gradient(135deg, #0891b2, #06b6d4); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(8,145,178,0.3); height: 36px; white-space: nowrap; }
        .btn-refresh:hover { background: linear-gradient(135deg, #0e7490, #0891b2); box-shadow: 0 4px 12px rgba(8,145,178,0.4); transform: translateY(-1px); }
        .btn-refresh:disabled { cursor: not-allowed; opacity: 0.5; }
        .btn-refresh .refresh-icon.spinning { animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .btn-yn-oldi-word { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(37,99,235,0.3); white-space: nowrap; height: 36px; }
        .btn-yn-oldi-word:hover:not(:disabled) { background: linear-gradient(135deg, #1d4ed8, #2563eb); box-shadow: 0 4px 12px rgba(37,99,235,0.4); transform: translateY(-1px); }
        .btn-yn-oldi-word:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-export-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); white-space: nowrap; height: 36px; }
        .btn-export-excel:hover { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }

        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .schedule-table thead { position: sticky; top: 0; z-index: 10; }
        .schedule-table thead tr.header-row { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .schedule-table thead tr.filter-header-row { background: #f0f4f8; }
        .schedule-table thead tr.filter-header-row th { padding: 4px 6px; border-bottom: 2px solid #93c5fd; }
        .col-filter { width: 100%; height: 26px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 11px; color: #334155; background: #fff; padding: 0 4px; cursor: pointer; outline: none; transition: all 0.2s; }
        .col-filter:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .col-filter:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .attempt-badge { display: inline-flex; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; background: #f0f4f8; color: #475569; letter-spacing: 0.02em; }
        .schedule-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .schedule-table th.sortable { cursor: pointer; user-select: none; transition: background 0.15s; }
        .schedule-table th.sortable:hover { background: rgba(43,94,167,0.1); }
        .sort-icon { font-size: 10px; color: #2b5ea7; }
        .data-row td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-row:hover td { background: #f8fafc; }

        .date-badge { display: inline-flex; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; line-height: 1.3; }
        .badge-pending { background: #dcfce7; color: #166534; }
        .badge-pending-blue { background: #dbeafe; color: #1e40af; }
        .badge-today { background: #fef9c3; color: #854d0e; }
        .badge-soon { background: #ffedd5; color: #9a3412; }
        .badge-passed { background: #f1f5f9; color: #64748b; }

        .status-badge { display: inline-flex; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; text-transform: uppercase; letter-spacing: 0.03em; }
        .status-pending { background: #dcfce7; color: #166534; }
        .status-today { background: #fef9c3; color: #854d0e; }
        .status-soon { background: #ffedd5; color: #9a3412; }
        .status-passed { background: #f1f5f9; color: #64748b; }
        .status-empty { background: #fef2f2; color: #dc2626; }
        .na-badge { display: inline-flex; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; background: #fef2f2; color: #dc2626; text-transform: uppercase; letter-spacing: 0.03em; }

        .yn-type-badge { display: inline-flex; padding: 4px 12px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; text-transform: uppercase; letter-spacing: 0.03em; }
        .yn-type-oski { background: #dcfce7; color: #166534; }
        .yn-type-test { background: #dbeafe; color: #1e40af; }

        .quiz-count-zero { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .quiz-count-partial { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .quiz-count-full { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        .yn-submitted-yes { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .yn-submitted-no { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .color-filter option[value="green"] { color: #16a34a; font-weight: 600; }
        .color-filter option[value="yellow"] { color: #d97706; font-weight: 600; }
        .color-filter option[value="red"] { color: #dc2626; font-weight: 600; }
    </style>
</x-app-layout>
