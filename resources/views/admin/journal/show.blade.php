<x-app-layout>
    <style>
        .journal-table {
            border: 1px solid #cbd5e1;
            width: auto;
            table-layout: auto;
        }
        .journal-table th,
        .journal-table td {
            border: 1px solid #94a3b8 !important;
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
        }
        .journal-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .journal-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .journal-table tbody td:hover {
            background-color: #e0f2fe !important;
        }
        .journal-table thead th {
            background-color: #f3f4f6;
        }
        .journal-table .date-header-cell {
            padding: 0 !important;
            vertical-align: middle;
        }
        .journal-table .date-header-cell .date-text-wrapper {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            height: 90px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            line-height: 1.2;
            margin: 0 auto;
            padding: 2px 0;
        }
        .journal-table .joriy-header {
            height: 44px;
            font-size: 13px;
            font-weight: 700 !important;
            letter-spacing: 0.02em;
        }
        .tab-container {
            background: #e5e7eb !important;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 10px 10px 0 10px;
            gap: 4px;
            width: 100%;
        }
        .journal-layout {
            display: flex;
            gap: 0;
            align-items: flex-start;
        }
        .journal-main-content {
            flex: 1;
            min-width: 0;
            overflow-x: auto;
        }
        .journal-sidebar {
            width: 280px;
            flex-shrink: 0;
            background: #f8fafc;
            border-left: 2px solid #e2e8f0;
            border-radius: 0 8px 8px 0;
            padding: 0;
            position: sticky;
            top: 0;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }
        .sidebar-header {
            background: #374151;
            color: #fff;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 0 8px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .sidebar-header-left {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sidebar-view-toggle {
            display: flex;
        }
        .sidebar-view-btn {
            padding: 3px 10px;
            font-size: 11px;
            border: 1px solid rgba(255,255,255,0.3);
            background: transparent;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            transition: all 0.2s;
        }
        .sidebar-view-btn:first-child {
            border-radius: 4px 0 0 4px;
        }
        .sidebar-view-btn:last-child {
            border-radius: 0 4px 4px 0;
            margin-left: -1px;
        }
        .sidebar-view-btn.active {
            background: rgba(255,255,255,0.2);
            color: #fff;
            border-color: rgba(255,255,255,0.5);
        }
        .sidebar-field {
            padding: 4px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .sidebar-field:last-child {
            border-bottom: none;
        }
        .sidebar-label {
            font-size: 11px;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .sidebar-section-label {
            font-size: 11px;
            color: #374151;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 6px 12px 2px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        .sidebar-info-text {
            width: 100%;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #1f2937;
        }
        .sidebar-teacher-card {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 5px 0;
        }
        .sidebar-teacher-card + .sidebar-teacher-card {
            border-top: 1px solid #f1f5f9;
        }
        .sidebar-teacher-name {
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            flex: 1;
            line-height: 1.3;
        }
        .sidebar-teacher-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .badge-lecture {
            background: #ede9fe;
            color: #6d28d9;
        }
        .badge-practice {
            background: #d1fae5;
            color: #047857;
        }
        .sidebar-teacher-type-label {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 1px 6px;
            border-radius: 3px;
            margin-bottom: 2px;
        }
        .type-label-lecture {
            background: #f3e8ff;
            color: #7c3aed;
        }
        .type-label-practice {
            background: #ecfdf5;
            color: #059669;
        }
        /* Mavzular bo'limi */
        .mavzular-section {
            margin-top: 16px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .mavzular-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .mavzular-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }
        .mavzular-body {
            max-height: 400px;
            overflow-y: auto;
        }
        .mavzular-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .mavzular-table thead th {
            background: #f1f5f9;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.05em;
            padding: 8px 10px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 1;
            border-bottom: 2px solid #e2e8f0;
        }
        .mavzular-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .mavzular-table tbody tr:hover {
            background: #f8fafc;
        }
        .mavzular-table tbody td {
            padding: 8px 10px;
            color: #334155;
            vertical-align: top;
        }
        .mavzular-table .topic-num {
            color: #94a3b8;
            font-weight: 600;
            text-align: center;
            width: 36px;
        }
        .mavzular-table .topic-hours {
            text-align: center;
            width: 60px;
        }
        .topic-hours-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .mavzular-table .topic-name {
            font-weight: 500;
            line-height: 1.4;
        }
        .mavzular-table .topic-date {
            color: #94a3b8;
            font-size: 11px;
            white-space: nowrap;
            width: 90px;
        }
        .mavzular-loading {
            padding: 24px;
            text-align: center;
            color: #94a3b8;
        }
        .mavzular-empty {
            padding: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }
        .mavzular-error {
            padding: 16px;
            text-align: center;
            color: #ef4444;
            font-size: 13px;
        }
        .sidebar-select {
            width: 100%;
            padding: 6px 28px 6px 10px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.15s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
        }
        .sidebar-select:hover {
            border-color: #3b82f6;
        }
        .sidebar-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .sidebar-value {
            font-size: 13px;
            font-weight: 500;
            color: #1f2937;
            padding: 6px 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .sidebar-loading {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #6b7280;
            padding: 4px 0;
        }
        .sidebar-loading.active {
            display: flex;
        }
        .sidebar-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @keyframes badge-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
        .tab-btn {
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 8px 8px 0 0;
            background: #d1d5db !important;
            color: #4b5563 !important;
            transition: all 0.2s;
            cursor: pointer;
            outline: none;
        }
        .tab-btn:hover:not(.active) {
            background: #c7cbd1 !important;
            color: #1f2937 !important;
        }
        .tab-btn.active {
            background: #f3f4f6 !important;
            color: #1f2937 !important;
            font-weight: 700;
        }
        .view-btn {
            padding: 4px 12px;
            font-size: 12px;
            border: 1px solid #d1d5db;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }
        .view-btn:first-child {
            border-radius: 4px 0 0 4px;
        }
        .view-btn:last-child {
            border-radius: 0 4px 4px 0;
            margin-left: -1px;
        }
        .view-btn.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        .tooltip-cell {
            position: relative;
            cursor: help;
        }
        .tooltip-cell:hover .tooltip-content {
            display: block;
        }
        .tooltip-content {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 100;
            margin-bottom: 4px;
        }
        .tooltip-content::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: #1f2937;
        }
        .date-separator {
            border-left: 2px solid #94a3b8 !important;
        }
        .date-end {
            border-right: 2px solid #94a3b8 !important;
        }
        .journal-table .detailed-date-start {
            border-left: 4px double #64748b !important;
        }
        .journal-table .detailed-date-end {
            border-right: 4px double #64748b !important;
        }
        .inconsistent-grade {
            background-color: #fef3c7 !important;
        }
        .grade-fail {
            color: #dc2626 !important;
        }
        .grade-retake {
            color: #7c3aed !important;
        }
        /* Diagonal split cell for retake grades */
        .split-cell {
            position: relative;
            width: 100%;
            height: 40px;
            overflow: hidden;
        }
        .split-cell .split-line {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        .split-cell .split-line line {
            stroke: #94a3b8;
            stroke-width: 1;
        }
        .split-cell .split-top {
            position: absolute;
            top: 2px;
            left: 4px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
        }
        .split-cell .split-bottom {
            position: absolute;
            bottom: 2px;
            right: 4px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
            color: #7c3aed;
        }
        .editable-cell {
            position: relative;
            min-height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .editable-cell:hover {
            background-color: #dbeafe !important;
            cursor: cell;
            border: 1px solid #3b82f6;
        }
        .editable-cell::after {
            content: '✎';
            position: absolute;
            top: 1px;
            right: 2px;
            font-size: 10px;
            color: #3b82f6;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .editable-cell:hover::after {
            opacity: 0.7;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Toggle tugma desktopda yashirin */
        .sidebar-mobile-toggle {
            display: none;
        }

        /* ===== SIDEBAR TOGGLE (1024px dan past - admin sidebar 256px + kontent) ===== */
        @media (max-width: 1024px) {
            .journal-layout {
                flex-direction: column;
            }
            .journal-sidebar {
                width: 100%;
                position: relative;
                max-height: none;
                border-left: none;
                border-top: 2px solid #e2e8f0;
                border-radius: 0 0 8px 8px;
            }
            .sidebar-header {
                border-radius: 0;
                cursor: pointer;
            }
            .sidebar-mobile-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: none;
                border: none;
                color: #fff;
                font-size: 14px;
                cursor: pointer;
                padding: 0 4px;
                transition: transform 0.3s;
            }
            .journal-sidebar.collapsed .sidebar-mobile-toggle {
                transform: rotate(180deg);
            }
            .journal-sidebar .sidebar-collapsible {
                overflow: hidden;
                transition: max-height 0.3s ease, opacity 0.2s ease;
                max-height: 2000px;
                opacity: 1;
            }
            .journal-sidebar.collapsed .sidebar-collapsible {
                max-height: 0;
                opacity: 0;
            }
        }

        /* ===== MOBILE RESPONSIVE STYLES (768px dan past) ===== */
        @media (max-width: 768px) {
            /* Tabs: smaller on mobile */
            .tab-container {
                padding: 6px 4px 0 4px;
                gap: 2px;
                flex-wrap: wrap;
            }
            .tab-btn {
                padding: 7px 10px;
                font-size: 11px;
            }
            #syncScheduleBtn {
                padding: 4px 8px !important;
                font-size: 11px !important;
            }

            /* Table: tighter cells */
            .journal-table th,
            .journal-table td {
                padding: 2px 1px !important;
                font-size: 10px;
            }
            .journal-table .date-header-cell {
                min-width: 32px !important;
                width: 32px !important;
                height: 80px !important;
            }
            .journal-table .date-header-cell .date-text-wrapper {
                height: 70px;
                font-size: 9px;
            }
            /* Detailed view date columns also smaller */
            .journal-table .date-header-cell[style*="min-width: 55px"] {
                min-width: 34px !important;
                width: 34px !important;
                height: 85px !important;
            }
            .journal-table .joriy-header {
                height: 32px;
                font-size: 11px;
            }

            /* Student name column: narrower on mobile */
            .journal-table th[style*="min-width: 180px"],
            .journal-table td.student-name-cell {
                min-width: 110px !important;
                max-width: 130px;
                white-space: normal !important;
                word-break: break-word;
                font-size: 10px;
                line-height: 1.2;
            }

            /* Summary columns narrower */
            .journal-table th[style*="width: 55px"],
            .journal-table th[style*="width: 50px"],
            .journal-table th[style*="width: 40px"] {
                min-width: 30px !important;
                width: 30px !important;
            }

            /* Split cell smaller */
            .split-cell {
                height: 32px;
            }
            .split-cell .split-top {
                font-size: 9px;
            }
            .split-cell .split-bottom {
                font-size: 9px;
            }

            /* Editable cell smaller */
            .editable-cell {
                min-height: 20px;
            }

            /* Main content padding */
            .max-w-full {
                padding-left: 4px !important;
                padding-right: 4px !important;
            }

            /* MT grade input smaller */
            .mt-grade-input {
                width: 48px !important;
                font-size: 11px !important;
                padding: 2px 3px !important;
            }

            /* MT action buttons smaller */
            #content-mustaqil button[onclick*="saveMtGrade"],
            #content-mustaqil button[onclick*="startRegrade"] {
                padding: 4px 10px !important;
                font-size: 11px !important;
            }

            /* Mavzular section fits mobile */
            .mavzular-table thead th,
            .mavzular-table tbody td {
                padding: 4px 6px;
                font-size: 10px;
            }

            /* Reduce top padding on mobile */
            .journal-page-wrapper {
                padding-top: 10px !important;
            }

            /* Back button and sync smaller */
            .mb-2 a {
                font-size: 12px !important;
            }
        }

        /* Extra-small phones */
        @media (max-width: 400px) {
            .tab-btn {
                padding: 6px 6px;
                font-size: 10px;
            }
            .journal-table .date-header-cell {
                min-width: 28px !important;
                width: 28px !important;
            }
            .journal-table .date-header-cell .date-text-wrapper {
                height: 60px;
                font-size: 8px;
            }
            .journal-table th[style*="min-width: 180px"],
            .journal-table td.student-name-cell {
                min-width: 90px !important;
                max-width: 110px;
                font-size: 9px;
            }
        }
    </style>

    @php
        $isDekan = is_active_dekan();
        $isRegistrator = is_active_registrator();
    @endphp
    <div class="py-2 journal-page-wrapper" style="padding-top: 15vh;">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Nazad tugma -->
            <div class="mb-2">
                <a href="javascript:void(0)" onclick="window.history.back()" style="display: inline-flex; align-items: center; gap: 6px; color: #1e40af; font-size: 14px; font-weight: 500; text-decoration: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Jurnal
                </a>
            </div>
            <!-- Full-width Tabs with View Toggle -->
            <div class="mb-4">
                <nav class="tab-container">
                    <div style="display: flex; align-items: flex-end; gap: 4px;">
                        <button id="tab-maruza" onclick="switchTab('maruza')"
                            class="tab-btn">
                            Ma'ruza
                        </button>
                        <button id="tab-amaliyot" onclick="switchTab('amaliyot')"
                            class="tab-btn active">
                            Amaliyot
                        </button>
                        <button id="tab-mustaqil" onclick="switchTab('mustaqil')"
                            class="tab-btn" style="position: relative;">
                            Mustaqil ta'lim
                            @if(($mtUngradedCount ?? 0) > 0)
                                <span style="position: absolute; top: -6px; right: -6px; display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 5px; font-size: 11px; font-weight: 700; color: #fff; background: {{ ($mtDangerCount ?? 0) > 0 ? '#ef4444' : '#f59e0b' }}; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);{{ ($mtDangerCount ?? 0) > 0 ? ' animation: badge-pulse 1.5s ease-in-out infinite;' : '' }}">{{ $mtUngradedCount }}</span>
                            @endif
                        </button>
                    </div>
                    <div style="display: flex; align-items: center; padding-bottom: 6px;">
                        <button id="syncScheduleBtn" onclick="syncSchedule()" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; color: #1e40af; background: #dbeafe; border: 1px solid #93c5fd; border-radius: 6px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#bfdbfe'" onmouseout="this.style.background='#dbeafe'">
                            <svg id="syncIcon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                            <span id="syncBtnText">Jadvalni yangilash</span>
                        </button>
                    </div>
                </nav>
            </div>

            <!-- Main Layout: Content + Sidebar -->
            <div class="journal-layout">
                <!-- Main content area -->
                <div class="journal-main-content">

            <!-- Ma'ruza Tab Content -->
            <div id="content-maruza" class="tab-content hidden">
                <div class="bg-white">
                    @if($students->isEmpty())
                        <div class="p-4 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        @php
                            $totalLectureDays = count($lectureLessonDates);
                            $lecturePairsByDate = collect($lectureColumns)
                                ->groupBy('date')
                                ->map(fn($items) => $items->pluck('pair')->values()->toArray())
                                ->toArray();
                        @endphp

                        <div id="mz-compact-view" class="overflow-x-auto">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="width: 35px;">T/R</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="min-width: 180px;">F.I.SH.</th>
                                        @forelse($lectureLessonDates as $idx => $date)
                                            <th class="font-bold text-gray-600 text-center date-header-cell {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($lectureLessonDates) - 1 ? 'date-end' : '' }}" style="min-width: 50px; width: 50px; height: 100px;">
                                                <div class="date-text-wrapper">{{ format_date($date) }}</div>
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">Bo'sh</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php $studentLecture = $lectureAttendance[$student->hemis_id] ?? []; @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs student-name-cell">{{ $student->full_name }}</td>
                                            @forelse($lectureLessonDates as $idx => $date)
                                                @php
                                                    $scheduledPairs = $lecturePairsByDate[$date] ?? [];
                                                    $isDayMarked = false;
                                                    $isAbsent = false;

                                                    foreach ($scheduledPairs as $pair) {
                                                        if (isset($lectureMarkedPairs[$date][$pair])) {
                                                            $isDayMarked = true;
                                                            if (($studentLecture[$date][$pair] ?? null) === 'NB') {
                                                                $isAbsent = true;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($lectureLessonDates) - 1 ? 'date-end' : '' }}">
                                                    @if($isAbsent)
                                                        <span class="text-red-600 font-medium">NB</span>
                                                    @elseif($isDayMarked)
                                                        <span class="text-green-600 font-bold">+</span>
                                                    @else
                                                        <span>&nbsp;</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center"><span>&nbsp;</span></td>
                                            @endforelse
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div id="mz-detailed-view" class="overflow-x-auto hidden mt-4 border-t pt-4">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="width: 35px;">T/R</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center" style="min-width: 180px;">F.I.SH.</th>
                                        @forelse($lectureColumns as $idx => $col)
                                            <th class="font-bold text-gray-600 text-center date-header-cell {{ $idx === 0 || $lectureColumns[$idx - 1]['date'] !== $col['date'] ? 'detailed-date-start' : '' }} {{ !isset($lectureColumns[$idx + 1]) || $lectureColumns[$idx + 1]['date'] !== $col['date'] ? 'detailed-date-end' : '' }}" style="min-width: 55px; width: 55px; height: 110px;">
                                                <div class="date-text-wrapper">{{ format_date($col['date']) }}({{ $col['pair'] }})</div>
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">Bo'sh</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php $studentLecture = $lectureAttendance[$student->hemis_id] ?? []; @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs student-name-cell">{{ $student->full_name }}</td>
                                            @forelse($lectureColumns as $idx => $col)
                                                @php
                                                    $lectureMark = $studentLecture[$col['date']][$col['pair']] ?? null;
                                                    $isMarkedPair = isset($lectureMarkedPairs[$col['date']][$col['pair']]);
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $idx === 0 || $lectureColumns[$idx - 1]['date'] !== $col['date'] ? 'detailed-date-start' : '' }} {{ !isset($lectureColumns[$idx + 1]) || $lectureColumns[$idx + 1]['date'] !== $col['date'] ? 'detailed-date-end' : '' }}">
                                                    @if($isMarkedPair && $lectureMark === 'NB')
                                                        <span class="text-red-600 font-medium">NB</span>
                                                    @elseif($isMarkedPair)
                                                        <span class="text-green-600 font-bold">+</span>
                                                    @else
                                                        <span>&nbsp;</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center"><span>&nbsp;</span></td>
                                            @endforelse
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Amaliyot Tab Content -->
            <div id="content-amaliyot" class="tab-content">
                <div class="bg-white">
                    @if(($mtUngradedCount ?? 0) > 0)
                        @php $isDanger = ($mtDangerCount ?? 0) > 0; @endphp
                        <div onclick="switchTab('mustaqil')" style="margin: 0 8px 8px; padding: 10px 14px; border-radius: 8px; border: 1px solid {{ $isDanger ? '#fca5a5' : '#fcd34d' }}; background: {{ $isDanger ? '#fef2f2' : '#fefce8' }}; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 16px;{{ $isDanger ? ' animation: badge-pulse 1.5s ease-in-out infinite;' : '' }}">&#9888;</span>
                            <span style="font-size: 12px; font-weight: 700; color: {{ $isDanger ? '#991b1b' : '#92400e' }};">
                                {{ $mtUngradedCount }} ta MT topshiriq baholanmagan!
                                @if($isDanger)
                                    ({{ $mtDangerCount }} tasi 3+ kun)
                                @endif
                            </span>
                            <span style="font-size: 12px; color: {{ $isDanger ? '#dc2626' : '#d97706' }}; text-decoration: underline; margin-left: auto;">MT tabiga o'tish &rarr;</span>
                        </div>
                    @endif
                    @if($students->isEmpty())
                        <div class="p-4 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        @php
                            $totalJbDays = count($jbLessonDates);
                            $totalMtDays = count($mtLessonDates);
                            $gradingCutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->subDay()->startOfDay();
                            $openLessonRoles = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];
                            $canOpenLesson = (auth()->guard('web')->user()?->hasAnyRole($openLessonRoles) ?? false)
                                || (auth()->guard('teacher')->user()?->hasAnyRole($openLessonRoles) ?? false);
                            $isOqituvchi = is_active_oqituvchi();
                            $missedDatesLookup = array_flip($missedDates ?? []);
                            $activeOpenedDatesLookup = array_flip($activeOpenedDates ?? []);
                            $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, function ($date) use ($gradingCutoffDate) {
                                return \Carbon\Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate);
                            }));
                            $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
                            $totalJbDaysForAverage = count($jbLessonDatesForAverage);
                        @endphp
                        <!-- Compact View (Ixcham) -->
                        <div id="jb-compact-view" class="overflow-x-auto">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if($totalJbDays > 0)
                                            <th colspan="{{ $totalJbDays }}" class="px-1 py-2 font-bold text-gray-700 text-center date-separator date-end joriy-header">Joriy nazorat (kunlik o'rtacha)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-2 font-bold text-gray-700 text-center joriy-header">JN</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">JN %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">ON %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">OSKI</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">Test</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 50px;">Dav %</th>
                                    </tr>
                                    <tr>
                                        @forelse($jbLessonDates as $idx => $date)
                                            @php
                                                $dateStr = \Carbon\Carbon::parse($date)->format('Y-m-d');
                                                $isMissed = isset($missedDatesLookup[$dateStr]);
                                                $isOpened = isset(($lessonOpeningsMap ?? [])[$dateStr]);
                                                $openingInfo = ($lessonOpeningsMap ?? [])[$dateStr] ?? null;
                                                $isActiveOpened = $openingInfo && $openingInfo['status'] === 'active';
                                            @endphp
                                            <th class="font-bold text-gray-600 text-center date-header-cell {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($jbLessonDates) - 1 ? 'date-end' : '' }}" style="min-width: 50px; width: 50px; height: 100px; position: relative; {{ $isMissed && !$isOpened ? 'background: #fef2f2;' : '' }}{{ $isActiveOpened ? 'background: #ecfdf5;' : '' }}">
                                                <div class="date-text-wrapper">{{ format_date($date) }}</div>
                                                @if($canOpenLesson && $isMissed && !$isOpened)
                                                    <div style="position: absolute; bottom: 2px; left: 50%; transform: translateX(-50%);" title="O'tkazib yuborilgan kun — Dars ochish">
                                                        <button type="button" onclick="openLessonModal('{{ $dateStr }}')" style="background: #ef4444; color: #fff; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; cursor: pointer; line-height: 18px; padding: 0;">!</button>
                                                    </div>
                                                @elseif($isOpened)
                                                    <div style="position: absolute; bottom: 2px; left: 50%; transform: translateX(-50%);">
                                                        <button type="button" onclick="showOpeningInfo({!! htmlspecialchars(json_encode([
                                                            'date' => format_date($date),
                                                            'opened_at' => $openingInfo['opened_at'],
                                                            'opened_by' => $openingInfo['opened_by_name'],
                                                            'deadline' => $openingInfo['deadline'],
                                                            'status' => $isActiveOpened ? 'active' : $openingInfo['status'],
                                                            'grade_count' => $openingInfo['grade_count'],
                                                            'last_grade_at' => $openingInfo['last_grade_at'],
                                                            'file_name' => $openingInfo['file_original_name'],
                                                            'file_url' => route('admin.journal.download-lesson-file', $openingInfo['id']),
                                                        ]), ENT_QUOTES, 'UTF-8') !!})" style="background: {{ $isActiveOpened ? '#10b981' : '#9ca3af' }}; color: #fff; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">&#128206;</button>
                                                    </div>
                                                @endif
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">-</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            $studentJbGrades = $jbGrades[$student->hemis_id] ?? [];
                                            $dailyAverages = [];
                                            $dailySum = 0;
                                            $hasRetakeInDay = [];
                                            foreach ($jbLessonDates as $date) {
                                                $dayGrades = $studentJbGrades[$date] ?? [];
                                                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                // Divide by total pairs in day, not just student's grades
                                                $dailyAverages[$date] = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                                if (isset($jbLessonDatesForAverageLookup[$date])) {
                                                    $dailySum += $dailyAverages[$date];
                                                }
                                                $hasRetakeInDay[$date] = count($dayGrades) > 0 && collect($dayGrades)->contains(fn($g) => $g['is_retake']);
                                            }
                                            $jnAverage = $totalJbDaysForAverage > 0
                                                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $mtDailySum = 0;
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                            }
                                            $mtAverage = $totalMtDays > 0
                                                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                : 0;
                                            // Manual MT grade overrides lesson-based average
                                            $manualMt = $manualMtGrades[$student->hemis_id] ?? null;
                                            if ($manualMt !== null) {
                                                $mtAverage = round((float) $manualMt, 0, PHP_ROUND_HALF_UP);
                                            }

                                            $other = $otherGrades[$student->hemis_id] ?? ['on' => null, 'oski' => null, 'test' => null];

                                            // Calculate attendance percentage with 2 decimal places
                                            $absentOff = $attendanceData[$student->hemis_id] ?? 0;
                                            $davomatPercent = $auditoriumHours > 0 ? round(($absentOff / $auditoriumHours) * 100, 2) : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs student-name-cell">{{ $student->full_name }}</td>
                                            @forelse($jbLessonDates as $idx => $date)
                                                @php
                                                    $dayGrades = $studentJbGrades[$date] ?? [];
                                                    $dayAbsences = $jbAbsences[$student->hemis_id][$date] ?? [];
                                                    $dayAvg = $dailyAverages[$date];
                                                    $hasGrades = count($dayGrades) > 0;
                                                    $hasAbsenceNoGrade = !$hasGrades && count($dayAbsences) > 0;
                                                    $gradeValues = $hasGrades ? array_map(fn($g) => round($g['grade'], 0), $dayGrades) : [];
                                                    $gradesText = implode(', ', $gradeValues);
                                                    $uniqueGrades = array_unique($gradeValues);
                                                    $isInconsistent = count($uniqueGrades) > 1;
                                                    $isRetake = $hasRetakeInDay[$date] ?? false;
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($jbLessonDates) - 1 ? 'date-end' : '' }} {{ count($dayGrades) > 1 ? 'tooltip-cell' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                    @if($hasGrades)
                                                        @php
                                                            $hasTeacherGradeInDay = collect($dayGrades)->contains(fn($g) => ($g['hemis_id'] ?? null) == 88888888);
                                                            $dayAvgColorClass = $dayAvg < 60 ? 'text-red-600' : ($hasTeacherGradeInDay ? 'text-green-600' : 'text-gray-900');
                                                        @endphp
                                                        <span class="{{ $isRetake ? 'grade-retake' : $dayAvgColorClass }} font-medium">{{ $dayAvg }}</span>
                                                        @if(count($dayGrades) > 1)
                                                            <span class="tooltip-content">{{ $gradesText }}</span>
                                                        @endif
                                                    @elseif($hasAbsenceNoGrade)
                                                        @php
                                                            $dayAttData = $jbAttendance[$student->hemis_id][$date] ?? [];
                                                            $daySababli = false;
                                                            foreach ($dayAbsences as $pairCode => $absData) {
                                                                $attForPair = $dayAttData[$pairCode] ?? null;
                                                                if ($attForPair && ((int) ($attForPair['absent_on'] ?? 0)) > 0) {
                                                                    $daySababli = true;
                                                                    break;
                                                                }
                                                            }
                                                        @endphp
                                                        <span class="{{ $daySababli ? 'text-green-600' : 'text-red-600' }} font-medium">NB</span>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="font-bold {{ $jnAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $jnAverage }}</span><span class="text-gray-400 text-xs"> ({{ $totalJbDaysForAverage }})</span></td>
                                            <td class="px-1 py-1 text-center mt-cell-{{ $student->hemis_id }}"><span class="font-bold" style="color: {{ $mtAverage < 60 ? '#dc2626' : '#2563eb' }};">{{ $mtAverage }}</span></td>
                                            <td class="px-1 py-1 text-center">{{ $other['on'] ? round($other['on'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['oski'] ? round($other['oski'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['test'] ? round($other['test'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center" title="Qoldirgan: {{ $absentOff }} soat / Aud. soat: {{ $auditoriumHours }}"><span class="{{ $davomatPercent >= 25 ? 'grade-fail font-bold' : 'text-gray-900' }}">{{ number_format($davomatPercent, 2) }}%</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Detailed View (Batafsil) -->
                        <div id="jb-detailed-view" class="overflow-x-auto hidden">
                            @php
                                // Group columns by date for separators
                                $prevDate = null;
                                $dateGroups = [];
                                foreach ($jbColumns as $idx => $col) {
                                    $dateGroups[$col['date']][] = $idx;
                                }
                            @endphp
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        @if(count($jbColumns) > 0)
                                            <th colspan="{{ count($jbColumns) }}" class="px-1 py-2 font-bold text-gray-700 text-center date-separator date-end joriy-header">Joriy nazorat (har bir dars)</th>
                                        @else
                                            <th colspan="1" class="px-1 py-2 font-bold text-gray-700 text-center joriy-header">JN</th>
                                        @endif
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">JN %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">MT %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">ON %</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">OSKI</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 40px;">Test</th>
                                        <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 50px;">Dav %</th>
                                    </tr>
                                    <tr>
                                        @php $prevDate = null; @endphp
                                        @forelse($jbColumns as $colIndex => $col)
                                            @php
                                                $isFirstOfDate = $prevDate !== $col['date'];
                                                $isLastOfDate = !isset($jbColumns[$colIndex + 1]) || $jbColumns[$colIndex + 1]['date'] !== $col['date'];
                                                $prevDate = $col['date'];
                                                $dDateStr = \Carbon\Carbon::parse($col['date'])->format('Y-m-d');
                                                $dIsMissed = isset($missedDatesLookup[$dDateStr]);
                                                $dOpeningInfo = ($lessonOpeningsMap ?? [])[$dDateStr] ?? null;
                                                $dIsActiveOpened = $dOpeningInfo && $dOpeningInfo['status'] === 'active';
                                            @endphp
                                            <th class="font-bold text-gray-600 text-center date-header-cell {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }}" style="min-width: 55px; width: 55px; height: 110px; position: relative; {{ $dIsMissed && !$dOpeningInfo ? 'background: #fef2f2;' : '' }}{{ $dIsActiveOpened ? 'background: #ecfdf5;' : '' }}">
                                                <div class="date-text-wrapper">{{ format_date($col['date']) }}({{ $col['pair'] }})</div>
                                                @if($canOpenLesson && $dIsMissed && !$dOpeningInfo && $isFirstOfDate)
                                                    <div style="position: absolute; bottom: 2px; left: 50%; transform: translateX(-50%);">
                                                        <button type="button" onclick="openLessonModal('{{ $dDateStr }}')" style="background: #ef4444; color: #fff; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; cursor: pointer; line-height: 18px; padding: 0;" title="Dars ochish">!</button>
                                                    </div>
                                                @elseif($dOpeningInfo)
                                                    <div style="position: absolute; bottom: 2px; left: 50%; transform: translateX(-50%);">
                                                        <button type="button" onclick="showOpeningInfo({!! htmlspecialchars(json_encode([
                                                            'date' => format_date($col['date']),
                                                            'opened_at' => $dOpeningInfo['opened_at'],
                                                            'opened_by' => $dOpeningInfo['opened_by_name'],
                                                            'deadline' => $dOpeningInfo['deadline'],
                                                            'status' => $dIsActiveOpened ? 'active' : $dOpeningInfo['status'],
                                                            'grade_count' => $dOpeningInfo['grade_count'],
                                                            'last_grade_at' => $dOpeningInfo['last_grade_at'],
                                                            'file_name' => $dOpeningInfo['file_original_name'],
                                                            'file_url' => route('admin.journal.download-lesson-file', $dOpeningInfo['id']),
                                                        ]), ENT_QUOTES, 'UTF-8') !!})" style="background: {{ $dIsActiveOpened ? '#10b981' : '#9ca3af' }}; color: #fff; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">&#128206;</button>
                                                    </div>
                                                @endif
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">-</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            $studentJbGrades = $jbGrades[$student->hemis_id] ?? [];
                                            $dailySum = 0;
                                            foreach ($jbLessonDates as $date) {
                                                $dayGrades = $studentJbGrades[$date] ?? [];
                                                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                                if (isset($jbLessonDatesForAverageLookup[$date])) {
                                                    $dailySum += $dayAverage;
                                                }
                                            }
                                            $jnAverage = $totalJbDaysForAverage > 0
                                                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                                                : 0;

                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $mtDailySum = 0;
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                            }
                                            $mtAverage = $totalMtDays > 0
                                                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                : 0;
                                            // Manual MT grade overrides lesson-based average
                                            $manualMt = $manualMtGrades[$student->hemis_id] ?? null;
                                            if ($manualMt !== null) {
                                                $mtAverage = round((float) $manualMt, 0, PHP_ROUND_HALF_UP);
                                            }

                                            $other = $otherGrades[$student->hemis_id] ?? ['on' => null, 'oski' => null, 'test' => null];

                                            $absentOff = $attendanceData[$student->hemis_id] ?? 0;
                                            $davomatPercent = $auditoriumHours > 0 ? round(($absentOff / $auditoriumHours) * 100, 2) : 0;
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs student-name-cell">{{ $student->full_name }}</td>
                                            @php $prevDate = null; @endphp
                                            @forelse($jbColumns as $colIndex => $col)
                                                @php
                                                    $gradeData = $studentJbGrades[$col['date']][$col['pair']] ?? null;
                                                    $grade = $gradeData ? $gradeData['grade'] : null;
                                                    $isRetake = $gradeData ? $gradeData['is_retake'] : false;
                                                    $isAbsent = isset($jbAbsences[$student->hemis_id][$col['date']][$col['pair']]);
                                                    $isFirstOfDate = $prevDate !== $col['date'];
                                                    $isLastOfDate = !isset($jbColumns[$colIndex + 1]) || $jbColumns[$colIndex + 1]['date'] !== $col['date'];
                                                    $prevDate = $col['date'];

                                                    $dayGrades = $studentJbGrades[$col['date']] ?? [];
                                                    $gradeValues = array_map(fn($g) => round($g['grade'], 0), $dayGrades);
                                                    $uniqueGrades = array_unique($gradeValues);
                                                    $isInconsistent = count($uniqueGrades) > 1;
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                    @php
                                                        $canRate = !$isDekan && auth()->user()->hasRole('admin');
                                                        $colDateStr = \Carbon\Carbon::parse($col['date'])->format('Y-m-d');
                                                        $isOpenedDate = isset($activeOpenedDatesLookup[$colDateStr]);
                                                        $canEditOpened = $isOpenedDate && $grade === null && !$isAbsent && $isOqituvchi;
                                                        $showRatingInput = false;
                                                        $gradeRecordId = null;
                                                        $hasRetake = false;
                                                        $isEmpty = false;
                                                        $retakeType = null;

                                                        if ($isAbsent && isset($jbAbsences[$student->hemis_id][$col['date']][$col['pair']])) {
                                                            $absenceData = $jbAbsences[$student->hemis_id][$col['date']][$col['pair']];
                                                            $gradeRecordId = $absenceData['id'];
                                                            $hasRetake = $absenceData['retake_grade'] !== null;
                                                            $showRatingInput = $canRate && !$hasRetake;
                                                            $retakeType = 'absent';
                                                        } elseif ($gradeData && $gradeData['reason'] === 'low_grade' && $gradeData['retake_grade'] !== null) {
                                                            // Otrabotka qilingan (original_grade < 60)
                                                            $hasRetake = true;
                                                            $retakeType = 'low_grade';
                                                        } elseif ($gradeData && $gradeData['original_grade'] !== null && round($gradeData['original_grade'], 0) < 60 && ($gradeData['retake_grade'] ?? null) === null) {
                                                            // 60 dan past, hali otrabotka qilinmagan
                                                            $gradeRecordId = $gradeData['id'];
                                                            $showRatingInput = $canRate;
                                                        } elseif (!$isAbsent && $grade === null) {
                                                            $isEmpty = true;
                                                            $showRatingInput = $canRate;
                                                        }
                                                    @endphp
                                                    @if($grade !== null)
                                                        @if($showRatingInput)
                                                            {{-- 60 dan past baho — otrabotka qilish mumkin --}}
                                                            <div class="editable-cell cursor-pointer hover:bg-blue-50" onclick="makeEditable(this, {{ $gradeRecordId }})" title="Bosib baho kiriting">
                                                                <span class="text-red-600 font-medium">{{ round($grade, 0) }}</span>
                                                            </div>
                                                        @elseif($hasRetake && $retakeType === 'low_grade')
                                                            {{-- 60 dan past + otrabotka qilgan: diagonal split --}}
                                                            @php
                                                                $origVal = round($gradeData['original_grade'], 0);
                                                                $retakeVal = round($gradeData['retake_grade'], 0);
                                                            @endphp
                                                            <div class="split-cell" title="Oldingi: {{ $origVal }}, Otrabotka: {{ $retakeVal }}">
                                                                <svg class="split-line" viewBox="0 0 100 100" preserveAspectRatio="none"><line x1="0" y1="100" x2="100" y2="0" /></svg>
                                                                <span class="split-top text-red-600">{{ $origVal }}</span>
                                                                <span class="split-bottom">{{ $retakeVal }}</span>
                                                            </div>
                                                        @else
                                                            @php
                                                                $isTeacherGrade = ($gradeData['hemis_id'] ?? null) == 88888888;
                                                                $gradeColorClass = round($grade, 0) < 60 ? 'text-red-600' : ($isTeacherGrade ? 'text-green-600' : 'text-gray-900');
                                                            @endphp
                                                            <span class="{{ $isRetake ? 'grade-retake' : $gradeColorClass }} font-medium">{{ round($grade, 0) }}</span>
                                                        @endif
                                                    @elseif($isAbsent)
                                                        @php
                                                            $absAttData = $jbAttendance[$student->hemis_id][$col['date']][$col['pair']] ?? null;
                                                            $isSababli = $absAttData && ((int) ($absAttData['absent_on'] ?? 0)) > 0;
                                                            $nbColorClass = $isSababli ? 'text-green-600' : 'text-red-600';
                                                        @endphp
                                                        @if($showRatingInput)
                                                            {{-- NB — otrabotka qilish mumkin --}}
                                                            <div class="editable-cell cursor-pointer hover:bg-blue-50" onclick="makeEditable(this, {{ $gradeRecordId }})" title="Bosib baho kiriting">
                                                                <span class="{{ $nbColorClass }} font-medium">NB</span>
                                                            </div>
                                                        @elseif($hasRetake)
                                                            {{-- NB + otrabotka qilgan: diagonal split --}}
                                                            @php $retakeVal = round($absenceData['retake_grade'], 0); @endphp
                                                            <div class="split-cell" title="NB ({{ $isSababli ? 'sababli' : 'sababsiz' }}), Otrabotka: {{ $retakeVal }}">
                                                                <svg class="split-line" viewBox="0 0 100 100" preserveAspectRatio="none"><line x1="0" y1="100" x2="100" y2="0" /></svg>
                                                                <span class="split-top {{ $nbColorClass }}" style="font-size:10px;">NB</span>
                                                                <span class="split-bottom">{{ $retakeVal }}</span>
                                                            </div>
                                                        @else
                                                            <span class="{{ $nbColorClass }} font-medium">NB</span>
                                                        @endif
                                                    @else
                                                        @if($canEditOpened && $isEmpty)
                                                            {{-- O'qituvchi uchun: ochilgan darsga baho qo'yish --}}
                                                            <div class="editable-cell grade-cell-opened cursor-pointer hover:bg-green-50"
                                                                 data-row="{{ $index }}" data-col="{{ $colIndex }}"
                                                                 data-student="{{ $student->hemis_id }}" data-date="{{ $col['date'] }}"
                                                                 data-pair="{{ $col['pair'] }}" data-subject="{{ $subjectId }}"
                                                                 data-semester="{{ $semesterCode }}" data-group="{{ $group->group_hemis_id }}"
                                                                 onclick="startEditOpened(this)"
                                                                 title="Dars ochilgan — baho kiriting" style="background: #f0fdf4;">
                                                                <span class="text-green-400">-</span>
                                                            </div>
                                                        @elseif($canRate && $isEmpty)
                                                            <div class="editable-cell cursor-pointer hover:bg-blue-50"
                                                                 onclick="makeEditableEmpty(this, '{{ $student->hemis_id }}', '{{ $col['date'] }}', '{{ $col['pair'] }}', '{{ $subjectId }}', '{{ $semesterCode }}')"
                                                                 title="Bosib baho kiriting">
                                                                <span class="text-gray-400">-</span>
                                                            </div>
                                                        @else
                                                            <span class="text-gray-300">-</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center"><span class="font-bold {{ $jnAverage < 60 ? 'grade-fail' : 'text-blue-600' }}">{{ $jnAverage }}</span><span class="text-gray-400 text-xs"> ({{ $totalJbDaysForAverage }})</span></td>
                                            <td class="px-1 py-1 text-center mt-cell-{{ $student->hemis_id }}"><span class="font-bold" style="color: {{ $mtAverage < 60 ? '#dc2626' : '#2563eb' }};">{{ $mtAverage }}</span></td>
                                            <td class="px-1 py-1 text-center">{{ $other['on'] ? round($other['on'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['oski'] ? round($other['oski'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center">{{ $other['test'] ? round($other['test'], 0, PHP_ROUND_HALF_UP) : '' }}</td>
                                            <td class="px-1 py-1 text-center" title="Qoldirgan: {{ $absentOff }} soat / Aud. soat: {{ $auditoriumHours }}"><span class="{{ $davomatPercent >= 25 ? 'grade-fail font-bold' : 'text-gray-900' }}">{{ number_format($davomatPercent, 2) }}%</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Mustaqil ta'lim Tab Content -->
            <div id="content-mustaqil" class="tab-content hidden">
                <div class="bg-white">
                    @if($students->isEmpty())
                        <div class="p-4 text-center text-gray-500">
                            <p>Bu guruhda talabalar mavjud emas.</p>
                        </div>
                    @else
                        @php
                            // Calculate warning count for the banner
                            $warningCount = 0;
                            foreach ($students as $st) {
                                $sub = $mtSubmissions[$st->hemis_id] ?? null;
                                $gr = $manualMtGrades[$st->hemis_id] ?? null;
                                if ($sub && $gr === null) {
                                    $days = \Carbon\Carbon::parse($sub->submitted_at)->diffInDays(now());
                                    if ($days >= 1 && $days < 3) $warningCount++;
                                }
                            }
                        @endphp

                        @if($mtUngradedCount > 0)
                            @php
                                if ($mtDangerCount > 0) { $bannerBg = '#fef2f2'; $bannerBorder = '#fca5a5'; $bannerColor = '#991b1b'; }
                                elseif ($warningCount > 0) { $bannerBg = '#fefce8'; $bannerBorder = '#fcd34d'; $bannerColor = '#92400e'; }
                                else { $bannerBg = '#eff6ff'; $bannerBorder = '#93c5fd'; $bannerColor = '#1e40af'; }
                            @endphp
                            <div style="margin: 0 8px 12px; padding: 10px 14px; border-radius: 8px; border: 1px solid {{ $bannerBorder }}; background: {{ $bannerBg }}; display: flex; align-items: center; gap: 8px;">
                                @if($mtDangerCount > 0)
                                    <span style="font-size: 18px; animation: badge-pulse 1.5s ease-in-out infinite;">&#9888;</span>
                                    <span style="font-size: 13px; font-weight: 700; color: {{ $bannerColor }};">{{ $mtUngradedCount }} ta baholanmagan topshiriq!</span>
                                    <span style="font-size: 11px; color: #dc2626;">({{ $mtDangerCount }} tasi 3+ kun kutmoqda)</span>
                                @elseif($warningCount > 0)
                                    <span style="font-size: 18px;">&#9888;</span>
                                    <span style="font-size: 13px; font-weight: 700; color: {{ $bannerColor }};">{{ $mtUngradedCount }} ta baholanmagan topshiriq</span>
                                    <span style="font-size: 11px; color: #d97706;">({{ $warningCount }} tasi 1+ kun kutmoqda)</span>
                                @else
                                    <span style="font-size: 18px;">&#128276;</span>
                                    <span style="font-size: 13px; font-weight: 600; color: {{ $bannerColor }};">{{ $mtUngradedCount }} ta yangi topshiriq baholashni kutmoqda</span>
                                @endif
                            </div>
                        @endif

                        <!-- Manual Grade Entry Table -->
                        <div class="overflow-x-auto">
                            <table class="journal-table border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 140px;">Fayl</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 80px;">Baho</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 160px;">Tarix</th>
                                        <th class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 110px;">Amal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            $manualGrade = $manualMtGrades[$student->hemis_id] ?? null;
                                            $gradeRow = $manualMtGradesRaw[$student->hemis_id] ?? null;
                                            $hasGrade = $manualGrade !== null;
                                            $isLockedPermanent = $hasGrade && $manualGrade >= 60;
                                            $history = $mtGradeHistory[$student->hemis_id] ?? [];
                                            $currentAttempt = count($history) + ($hasGrade ? 1 : 0);
                                            $submission = $mtSubmissions[$student->hemis_id] ?? null;
                                            $hasFile = $submission !== null;

                                            // canRegrade: talaba qayta yuklagan bo'lsa (submitted_at > grade updated_at)
                                            $hasResubmitted = false;
                                            if ($hasGrade && $hasFile && $gradeRow) {
                                                $gradeTime = $gradeRow->updated_at ?? $gradeRow->created_at;
                                                $submitTime = $submission->submitted_at;
                                                if ($gradeTime && $submitTime) {
                                                    $hasResubmitted = \Carbon\Carbon::parse($submitTime)->gt(\Carbon\Carbon::parse($gradeTime));
                                                }
                                            }
                                            $canRegrade = $hasGrade && $manualGrade < 60 && $currentAttempt <= $mtMaxResubmissions && $hasResubmitted;
                                            $inputDisabled = $isDekan || $isRegistrator || $hasGrade || !$hasFile;

                                            // Urgency: file uploaded but not graded, OR resubmitted after low grade
                                            $urgency = 'none'; // none, fresh, warning, danger
                                            $needsGrading = ($hasFile && !$hasGrade) || $hasResubmitted;
                                            if ($needsGrading && $hasFile) {
                                                $submittedAt = \Carbon\Carbon::parse($submission->submitted_at);
                                                $daysSince = $submittedAt->diffInDays(now());
                                                if ($daysSince >= 3) {
                                                    $urgency = 'danger';
                                                } elseif ($daysSince >= 1) {
                                                    $urgency = 'warning';
                                                } else {
                                                    $urgency = 'fresh';
                                                }
                                            }
                                            $rowClass = match($urgency) {
                                                'danger' => 'bg-red-50',
                                                'warning' => 'bg-yellow-50',
                                                default => '',
                                            };
                                        @endphp
                                        @php
                                            $rowBg = match($urgency) {
                                                'danger' => '#fef2f2',
                                                'warning' => '#fefce8',
                                                default => '',
                                            };
                                        @endphp
                                        <tr id="mt-row-{{ $student->hemis_id }}" {!! $rowBg ? 'style="background:' . $rowBg . '"' : '' !!}>
                                            <td class="px-2 py-1 text-center" style="color: #111827;">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 uppercase student-name-cell" style="font-size: 12px; color: #111827;">{{ $student->full_name }}</td>
                                            <td class="px-1 py-1 text-center" id="mt-file-{{ $student->hemis_id }}">
                                                @if($hasFile)
                                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                                        <a href="{{ route('admin.journal.download-submission', $submission->id) }}"
                                                           style="color: #2563eb; font-size: 12px; text-decoration: none; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block;"
                                                           title="{{ $student->full_name }} {{ $subject->subject_name }}_MT">
                                                            {{ Str::limit($submission->file_original_name, 20) }}
                                                        </a>
                                                        @if($urgency === 'warning')
                                                            <span style="font-size: 11px; color: #ca8a04; font-weight: 500;">{{ $daysSince }} kun o'tdi</span>
                                                        @elseif($urgency === 'danger')
                                                            <span style="font-size: 11px; color: #dc2626; font-weight: 700; animation: badge-pulse 1.5s ease-in-out infinite;">{{ $daysSince }} kun o'tdi!</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span style="color: #f87171; font-size: 12px; font-weight: 500;">Yuklanmagan</span>
                                                @endif
                                            </td>
                                            <td class="px-1 py-1 text-center">
                                                @if($hasFile)
                                                    <input type="number"
                                                        id="mt-grade-{{ $student->hemis_id }}"
                                                        class="mt-grade-input"
                                                        style="width: 60px; padding: 3px 4px; text-align: center; font-size: 12px; border: 1px solid #d1d5db; border-radius: 4px; outline: none; {{ $inputDisabled ? 'background: #f3f4f6; color: #6b7280;' : 'color: #111827;' }}"
                                                        min="0" max="100" step="1"
                                                        value="{{ $hasGrade ? round($manualGrade) : '' }}"
                                                        data-student-id="{{ $student->hemis_id }}"
                                                        placeholder="0-100"
                                                        {{ $inputDisabled ? 'disabled' : '' }}>
                                                @else
                                                    <span style="color: #d1d5db;">—</span>
                                                @endif
                                            </td>
                                            <td class="px-1 py-1 text-center" id="mt-history-{{ $student->hemis_id }}">
                                                @if(count($history) > 0)
                                                    @foreach($history as $h)
                                                        <span style="display: inline-flex; align-items: center; padding: 2px 6px; font-size: 11px; border-radius: 4px; {{ $h->grade >= 60 ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' }} margin-right: 2px;" title="{{ $h->attempt_number }}-urinish: {{ $h->graded_by ?? '' }}">
                                                            {{ $h->attempt_number }}: {{ round($h->grade) }}
                                                            @if($h->file_path)
                                                                <a href="{{ route('admin.journal.download-history-file', $h->id) }}" style="margin-left: 2px; color: inherit;" title="{{ $h->attempt_number }}-urinish fayli">&#128206;</a>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                @else
                                                    <span style="color: #d1d5db;">—</span>
                                                @endif
                                            </td>
                                            <td class="px-1 py-1 text-center" id="mt-action-{{ $student->hemis_id }}">
                                                @if(!$hasFile)
                                                    {{-- No file: cannot grade --}}
                                                    <span style="color: #9ca3af; font-size: 12px;">—</span>
                                                @elseif(!$hasGrade)
                                                    {{-- Has file, no grade yet: show Save button --}}
                                                    @if(!$isDekan)
                                                    <button type="button"
                                                        onclick="saveMtGrade('{{ $student->hemis_id }}')"
                                                        style="padding: 6px 16px; font-size: 13px; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                                                        Saqlash
                                                    </button>
                                                    @endif
                                                @elseif($isLockedPermanent)
                                                    {{-- Grade >= 60: permanently locked --}}
                                                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; font-size: 12px; background: #dcfce7; color: #15803d; border-radius: 6px;">
                                                        &#128274; Qabul qilindi
                                                    </span>
                                                @elseif($canRegrade)
                                                    {{-- Grade < 60, student resubmitted: can regrade --}}
                                                    @if(!$isDekan)
                                                    <button type="button"
                                                        onclick="startRegrade('{{ $student->hemis_id }}')"
                                                        style="padding: 6px 16px; font-size: 13px; font-weight: 600; background: #f97316; color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                                                        Qayta baholash
                                                    </button>
                                                    @endif
                                                @elseif($hasGrade && $manualGrade < 60 && $currentAttempt <= $mtMaxResubmissions)
                                                    {{-- Grade < 60, waiting for student to resubmit --}}
                                                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; font-size: 12px; background: #fef9c3; color: #92400e; border-radius: 6px;">
                                                        &#128274; Kutilmoqda
                                                    </span>
                                                @else
                                                    {{-- Grade < 60, max attempts reached --}}
                                                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; font-size: 12px; background: #fee2e2; color: #b91c1c; border-radius: 6px;">
                                                        Limit tugagan
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @php
                            $totalMtDays = count($mtLessonDates);
                        @endphp

                        @if($totalMtDays > 0)
                        <div class="mt-4 border-t pt-4">
                            <h4 class="text-sm font-bold text-gray-700 mb-2">API'dan kelgan baholar</h4>
                            <!-- Compact View (Ixcham) -->
                            <div id="mt-compact-view" class="overflow-x-auto">
                                <table class="journal-table border-collapse text-xs">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                            <th colspan="{{ $totalMtDays }}" class="px-1 py-2 font-bold text-gray-700 text-center date-separator date-end joriy-header">Mustaqil ta'lim (kunlik o'rtacha)</th>
                                            <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">MT %</th>
                                        </tr>
                                        <tr>
                                            @foreach($mtLessonDates as $idx => $date)
                                                <th class="font-bold text-gray-600 text-center date-header-cell {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($mtLessonDates) - 1 ? 'date-end' : '' }}" style="min-width: 50px; width: 50px; height: 100px;">
                                                    <div class="date-text-wrapper">{{ format_date($date) }}</div>
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($students as $index => $student)
                                            @php
                                                $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                                $dailyAverages = [];
                                                $dailySum = 0;
                                                $hasRetakeInDay = [];
                                                foreach ($mtLessonDates as $date) {
                                                    $dayGrades = $studentMtGrades[$date] ?? [];
                                                    $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                    $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                    $gradeSum = array_sum($gradeValues);
                                                    $dailyAverages[$date] = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                                    $dailySum += $dailyAverages[$date];
                                                    $hasRetakeInDay[$date] = count($dayGrades) > 0 && collect($dayGrades)->contains(fn($g) => $g['is_retake']);
                                                }
                                                $mtAverage = $totalMtDays > 0
                                                    ? round($dailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                    : 0;
                                                // Manual MT grade overrides lesson-based average
                                                $manualMt = $manualMtGrades[$student->hemis_id] ?? null;
                                                if ($manualMt !== null) {
                                                    $mtAverage = round((float) $manualMt, 0, PHP_ROUND_HALF_UP);
                                                }
                                            @endphp
                                            <tr>
                                                <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                                <td class="px-2 py-1 text-gray-900 uppercase text-xs student-name-cell">{{ $student->full_name }}</td>
                                                @foreach($mtLessonDates as $idx => $date)
                                                    @php
                                                        $dayGrades = $studentMtGrades[$date] ?? [];
                                                        $dayAbsences = $mtAbsences[$student->hemis_id][$date] ?? [];
                                                        $dayAvg = $dailyAverages[$date];
                                                        $hasGrades = count($dayGrades) > 0;
                                                        $hasAbsenceNoGrade = !$hasGrades && count($dayAbsences) > 0;
                                                        $gradeValues = $hasGrades ? array_map(fn($g) => round($g['grade'], 0), $dayGrades) : [];
                                                        $gradesText = implode(', ', $gradeValues);
                                                        $uniqueGrades = array_unique($gradeValues);
                                                        $isInconsistent = count($uniqueGrades) > 1;
                                                        $isRetake = $hasRetakeInDay[$date] ?? false;
                                                    @endphp
                                                    <td class="px-1 py-1 text-center {{ $idx === 0 ? 'date-separator' : '' }} {{ $idx === count($mtLessonDates) - 1 ? 'date-end' : '' }} {{ count($dayGrades) > 1 ? 'tooltip-cell' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                        @if($hasGrades)
                                                            <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ $dayAvg }}</span>
                                                            @if(count($dayGrades) > 1)
                                                                <span class="tooltip-content">{{ $gradesText }}</span>
                                                            @endif
                                                        @elseif($hasAbsenceNoGrade)
                                                            <span class="text-red-600 font-medium">NB</span>
                                                        @else
                                                            <span class="text-gray-300">-</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td class="px-1 py-1 text-center mt-cell-{{ $student->hemis_id }}"><span class="font-bold" style="color: {{ $mtAverage < 60 ? '#dc2626' : '#2563eb' }};">{{ $mtAverage }}</span><span style="color: #9ca3af; font-size: 11px;"> ({{ $totalMtDays }})</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Detailed View (Batafsil) -->
                            <div id="mt-detailed-view" class="overflow-x-auto hidden">
                                <table class="journal-table border-collapse text-xs">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="width: 35px;">T/R</th>
                                            <th rowspan="2" class="px-2 py-1 font-bold text-gray-700 text-center align-middle" style="min-width: 180px;">F.I.SH.</th>
                                            @if(count($mtColumns) > 0)
                                                <th colspan="{{ count($mtColumns) }}" class="px-1 py-2 font-bold text-gray-700 text-center date-separator date-end joriy-header">Mustaqil ta'lim (har bir dars)</th>
                                            @else
                                                <th colspan="1" class="px-1 py-2 font-bold text-gray-700 text-center joriy-header">MT</th>
                                            @endif
                                            <th rowspan="2" class="px-1 py-1 font-bold text-gray-700 text-center align-middle" style="width: 55px;">MT %</th>
                                    </tr>
                                    <tr>
                                        @php $prevDate = null; @endphp
                                        @forelse($mtColumns as $colIndex => $col)
                                            @php
                                                $isFirstOfDate = $prevDate !== $col['date'];
                                                $isLastOfDate = !isset($mtColumns[$colIndex + 1]) || $mtColumns[$colIndex + 1]['date'] !== $col['date'];
                                                $prevDate = $col['date'];
                                            @endphp
                                            <th class="font-bold text-gray-600 text-center date-header-cell {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }}" style="min-width: 55px; width: 55px; height: 110px;">
                                                <div class="date-text-wrapper">{{ format_date($col['date']) }}({{ $col['pair'] }})</div>
                                            </th>
                                        @empty
                                            <th class="px-1 py-1 text-gray-400 text-center">-</th>
                                        @endforelse
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $index => $student)
                                        @php
                                            $studentMtGrades = $mtGrades[$student->hemis_id] ?? [];
                                            $dailySum = 0;
                                            foreach ($mtLessonDates as $date) {
                                                $dayGrades = $studentMtGrades[$date] ?? [];
                                                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                                                $gradeValues = array_map(fn($g) => $g['grade'], $dayGrades);
                                                $gradeSum = array_sum($gradeValues);
                                                $dailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                                            }
                                            $mtAverage = $totalMtDays > 0
                                                ? round($dailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                                                : 0;
                                            // Manual MT grade overrides lesson-based average
                                            $manualMt = $manualMtGrades[$student->hemis_id] ?? null;
                                            if ($manualMt !== null) {
                                                $mtAverage = round((float) $manualMt, 0, PHP_ROUND_HALF_UP);
                                            }
                                        @endphp
                                        <tr>
                                            <td class="px-2 py-1 text-gray-900 text-center">{{ $index + 1 }}</td>
                                            <td class="px-2 py-1 text-gray-900 uppercase text-xs student-name-cell">{{ $student->full_name }}</td>
                                            @php $prevDate = null; @endphp
                                            @forelse($mtColumns as $colIndex => $col)
                                                @php
                                                    $gradeData = $studentMtGrades[$col['date']][$col['pair']] ?? null;
                                                    $grade = $gradeData ? $gradeData['grade'] : null;
                                                    $isRetake = $gradeData ? $gradeData['is_retake'] : false;
                                                    $isAbsent = isset($mtAbsences[$student->hemis_id][$col['date']][$col['pair']]);
                                                    $isFirstOfDate = $prevDate !== $col['date'];
                                                    $isLastOfDate = !isset($mtColumns[$colIndex + 1]) || $mtColumns[$colIndex + 1]['date'] !== $col['date'];
                                                    $prevDate = $col['date'];

                                                    $dayGrades = $studentMtGrades[$col['date']] ?? [];
                                                    $gradeValues = array_map(fn($g) => round($g['grade'], 0), $dayGrades);
                                                    $uniqueGrades = array_unique($gradeValues);
                                                    $isInconsistent = count($uniqueGrades) > 1;
                                                @endphp
                                                <td class="px-1 py-1 text-center {{ $isFirstOfDate ? 'detailed-date-start' : '' }} {{ $isLastOfDate ? 'detailed-date-end' : '' }} {{ $isInconsistent ? 'inconsistent-grade' : '' }}">
                                                    @if($grade !== null)
                                                        <span class="{{ $isRetake ? 'grade-retake' : 'text-gray-900' }} font-medium">{{ round($grade, 0) }}</span>
                                                    @elseif($isAbsent)
                                                        <span class="text-red-600 font-medium">NB</span>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                </td>
                                            @empty
                                                <td class="px-1 py-1 text-center text-gray-300">-</td>
                                            @endforelse
                                            <td class="px-1 py-1 text-center mt-cell-{{ $student->hemis_id }}"><span class="font-bold" style="color: {{ $mtAverage < 60 ? '#dc2626' : '#2563eb' }};">{{ $mtAverage }}</span><span style="color: #9ca3af; font-size: 11px;"> ({{ $totalMtDays }})</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        </div>
                        @endif
                    @endif
                </div>
            </div>

                </div><!-- /.journal-main-content -->

                <!-- Right Sidebar: Filters -->
                <div class="journal-sidebar" id="journalSidebar">
                    <div class="sidebar-header" onclick="toggleMobileSidebar()">
                        <div class="sidebar-header-left">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                            Filtrlar
                            <button class="sidebar-mobile-toggle" id="sidebarToggleBtn" aria-label="Filtrlarni ochish/yopish">&#9660;</button>
                        </div>
                        <div class="sidebar-view-toggle">
                            <button id="view-compact" onclick="switchView('compact')" class="sidebar-view-btn active">Ixcham</button>
                            <button id="view-detailed" onclick="switchView('detailed')" class="sidebar-view-btn">Batafsil</button>
                        </div>
                    </div>

                    <div class="sidebar-collapsible">
                    <div class="sidebar-field">
                        <select id="filter-faculty" class="sidebar-select" style="font-size: 12px;" onchange="onFacultyChange()">
                            <option value="">Barchasi</option>
                            @if($facultyId)
                                <option value="{{ $facultyId }}" selected>{{ $facultyName }}</option>
                            @endif
                        </select>
                        <div id="loading-faculty" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <div class="sidebar-field">
                        <select id="filter-specialty" class="sidebar-select" style="font-size: 12px;" onchange="onSpecialtyChange()">
                            <option value="">Barchasi</option>
                            @if($specialtyId)
                                <option value="{{ $specialtyId }}" selected>{{ $specialtyName }}</option>
                            @endif
                        </select>
                        <div id="loading-specialty" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <div class="sidebar-field">
                        <div id="kafedra-display" class="sidebar-info-text">{{ $kafedraName }}</div>
                    </div>

                    <div class="sidebar-field">
                        <select id="filter-level" class="sidebar-select" onchange="onLevelChange()">
                            <option value="">Barchasi</option>
                            @if($levelCode)
                                <option value="{{ $levelCode }}" selected>{{ $kursName }}</option>
                            @endif
                        </select>
                        <div id="loading-level" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <div class="sidebar-field">
                        <select id="filter-semester" class="sidebar-select" onchange="onSemesterChange()">
                            <option value="">Barchasi</option>
                            @if($semesterCode)
                                <option value="{{ $semesterCode }}" selected>{{ $semester?->name ?? $subject->semester_name }}</option>
                            @endif
                        </select>
                        <div id="loading-semester" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <div class="sidebar-field">
                        <select id="filter-group" class="sidebar-select" onchange="onGroupChange(this.value)">
                            <option value="{{ $groupId }}" selected>{{ $group->name }}</option>
                        </select>
                        <div id="loading-group" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <div class="sidebar-field">
                        <select id="filter-subject" class="sidebar-select" style="font-size: 12px;" onchange="onSubjectChange(this.value)">
                            <option value="{{ $subjectId }}" selected>{{ $subject->subject_name }}</option>
                        </select>
                        <div id="loading-subject" class="sidebar-loading"><div class="sidebar-spinner"></div> Yuklanmoqda...</div>
                    </div>

                    <!-- O'qituvchilar -->
                    <div class="sidebar-section-label">O'qituvchilar</div>
                    <div class="sidebar-field" id="teachers-section" style="padding: 6px 12px;">
                        <div>
                            <div class="sidebar-teacher-type-label type-label-lecture">Ma'ruza</div>
                            <div id="lecture-teacher-display">
                                @if($lectureTeacher)
                                    <div class="sidebar-teacher-card">
                                        <div class="sidebar-teacher-name">{{ $lectureTeacher['name'] }}</div>
                                        <div class="sidebar-teacher-badge badge-lecture">{{ $lectureTeacher['hours'] }} soat</div>
                                    </div>
                                @else
                                    <div class="sidebar-teacher-card"><div class="sidebar-teacher-name" style="color:#9ca3af;">-</div></div>
                                @endif
                            </div>
                        </div>
                        <div style="margin-top: 4px;">
                            <div class="sidebar-teacher-type-label type-label-practice">Amaliyot</div>
                            <div id="practice-teacher-display">
                                @if(count($practiceTeachers) > 0)
                                    @foreach($practiceTeachers as $pt)
                                        <div class="sidebar-teacher-card">
                                            <div class="sidebar-teacher-name">{{ $pt['name'] }}</div>
                                            <div class="sidebar-teacher-badge badge-practice">{{ $pt['hours'] }} soat</div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="sidebar-teacher-card"><div class="sidebar-teacher-name" style="color:#9ca3af;">-</div></div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Talabalar soni -->
                    <div class="sidebar-field" style="background: #eff6ff; padding: 6px 12px;">
                        <div class="sidebar-label">Talabalar soni</div>
                        <div class="sidebar-value" style="font-weight: 700; color: #2563eb; border-color: #bfdbfe;">{{ $students->count() }}</div>
                    </div>
                    </div><!-- /.sidebar-collapsible -->
                </div>

            </div><!-- /.journal-layout -->

            <!-- Mavzular bo'limi -->
            <div class="mavzular-section" id="mavzular-section">
                <div class="mavzular-header">
                    <div class="mavzular-title">Mavzular</div>
                </div>
                <div class="mavzular-body" id="mavzular-body">
                    <div class="mavzular-loading" id="mavzular-loading">
                        <div class="sidebar-spinner" style="margin: 0 auto 8px;"></div>
                        Mavzular yuklanmoqda...
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // ====== Cascading Sidebar Filters ======
        // Zanjir: Fakultet(erkin) → Yo'nalish → Kurs → Semestr → [Guruh ↔ Fan]
        // Kafedra erkin, fanga ta'sir qiladi.
        const currentGroupId = '{{ $groupId }}';
        const currentGroupHemisId = '{{ $group->group_hemis_id }}';
        const currentSubjectId = '{{ $subjectId }}';
        const currentSemesterCode = '{{ $semesterCode }}';
        const currentFacultyId = '{{ $facultyId }}';
        const currentSpecialtyId = '{{ $specialtyId }}';
        const currentLevelCode = '{{ $levelCode }}';
        const journalShowBaseUrl = '{{ url("/admin/journal/show") }}';
        const sidebarOptionsUrl = '{{ route("admin.journal.get-sidebar-options") }}';
        const topicsUrl = '{{ route("admin.journal.get-topics") }}';
        const currentSemesterHemisId = '{{ $semester?->semester_hemis_id ?? '' }}';
        const currentCurriculumHemisId = '{{ $group->curriculum_hemis_id ?? '' }}';

        // ====== Jadval sinxronizatsiya ======
        function syncSchedule() {
            const btn = document.getElementById('syncScheduleBtn');
            const icon = document.getElementById('syncIcon');
            const text = document.getElementById('syncBtnText');

            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
            icon.style.animation = 'spin 1s linear infinite';
            text.textContent = 'Sinxronlanmoqda...';

            fetch('{{ route("admin.journal.sync-schedule") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    group_id: currentGroupHemisId,
                    subject_id: currentSubjectId
                })
            })
            .then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })))
            .then(({ ok, status, data }) => {
                icon.style.animation = '';
                if (ok && data.success) {
                    text.textContent = 'Yangilandi!';
                    btn.style.background = '#d1fae5';
                    btn.style.color = '#065f46';
                    btn.style.borderColor = '#6ee7b7';
                    setTimeout(() => { location.reload(); }, 2000);
                } else if (status === 429) {
                    text.textContent = data.message || '5 daqiqa kuting';
                    btn.style.background = '#fef3c7';
                    btn.style.color = '#92400e';
                    btn.style.borderColor = '#fcd34d';
                    setTimeout(() => resetBtn(), 10000);
                } else {
                    text.textContent = data.message || 'Xatolik';
                    btn.style.background = '#fee2e2';
                    btn.style.color = '#991b1b';
                    btn.style.borderColor = '#fca5a5';
                    setTimeout(() => resetBtn(), 10000);
                }
            })
            .catch(() => {
                icon.style.animation = '';
                text.textContent = 'Tarmoq xatoligi';
                btn.style.background = '#fee2e2';
                btn.style.color = '#991b1b';
                setTimeout(() => resetBtn(), 10000);
            });

            function resetBtn() {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
                btn.style.background = '#dbeafe';
                btn.style.color = '#1e40af';
                btn.style.borderColor = '#93c5fd';
                text.textContent = 'Jadvalni yangilash';
            }
        }

        // URL params - navigatsiyada filtr qiymatlarini saqlash uchun
        const urlParams = new URLSearchParams(window.location.search);

        function getVal(id) { return document.getElementById(id)?.value || ''; }

        function getFilterValues() {
            return {
                faculty_id: getVal('filter-faculty'),
                specialty_id: getVal('filter-specialty'),
                level_code: getVal('filter-level'),
                semester_code: getVal('filter-semester'),
                group_id: getVal('filter-group'),
                subject_id: getVal('filter-subject'),
            };
        }

        function buildQS(params) {
            return Object.entries(params)
                .filter(([k, v]) => v !== '')
                .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
                .join('&');
        }

        function navigateToJournal(groupId, subjectId, semesterCode) {
            const narrowParams = {
                faculty_id: getVal('filter-faculty'),
                specialty_id: getVal('filter-specialty'),
                level_code: getVal('filter-level'),
            };
            let url = `${journalShowBaseUrl}/${groupId}/${subjectId}/${semesterCode}`;
            const qs = Object.entries(narrowParams)
                .filter(([k, v]) => v !== '')
                .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
                .join('&');
            if (qs) url += '?' + qs;
            window.location.href = url;
        }

        function setLoading(field, show) {
            const el = document.getElementById('loading-' + field);
            if (el) el.classList.toggle('active', show);
        }

        function populateSelect(selectId, data, currentValue, addAll) {
            const select = document.getElementById(selectId);
            if (!select) return;
            select.innerHTML = '';
            if (addAll) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Barchasi';
                select.appendChild(opt);
            }
            let found = false;
            const entries = Object.entries(data).sort((a, b) => a[1].localeCompare(b[1]));
            for (const [key, value] of entries) {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = value;
                if (String(key) === String(currentValue)) {
                    opt.selected = true;
                    found = true;
                }
                select.appendChild(opt);
            }
            if (!found && !addAll && select.options.length > 0) {
                select.options[0].selected = true;
            }
        }

        // Kaskad reset - ota filtr o'zgarganda bolalar tozalanadi
        function cascadeReset(from) {
            const chains = {
                'faculty':   ['specialty', 'level', 'semester', 'group', 'subject'],
                'specialty': ['level', 'semester', 'group', 'subject'],
                'level':     ['semester', 'group', 'subject'],
                'semester':  ['group', 'subject'],
            };
            (chains[from] || []).forEach(f => {
                const el = document.getElementById('filter-' + f);
                if (el) el.value = '';
            });
        }

        // Barcha dropdownlarni yangilash
        let abortCtrl = null;
        function refreshFilters(overrideValues) {
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();

            const values = overrideValues || getFilterValues();
            const qs = buildQS(values);

            const fields = ['faculty', 'specialty', 'level', 'semester', 'group', 'subject'];
            fields.forEach(f => setLoading(f, true));

            fetch(`${sidebarOptionsUrl}?${qs}`, { signal: abortCtrl.signal })
                .then(r => r.json())
                .then(data => {
                    populateSelect('filter-faculty', data.faculties, values.faculty_id, true);
                    populateSelect('filter-specialty', data.specialties, values.specialty_id, true);
                    populateSelect('filter-level', data.levels, values.level_code, true);
                    populateSelect('filter-semester', data.semesters, values.semester_code, true);
                    populateSelect('filter-group', data.groups, values.group_id, false);
                    populateSelect('filter-subject', data.subjects, values.subject_id, false);
                    // O'qituvchi ma'lumotlarini yangilash (tur bo'yicha)
                    const lectureEl = document.getElementById('lecture-teacher-display');
                    const practiceEl = document.getElementById('practice-teacher-display');
                    if (lectureEl) {
                        if (data.teacher_data && data.teacher_data.lecture_teacher) {
                            const t = data.teacher_data.lecture_teacher;
                            lectureEl.innerHTML = '<div class="sidebar-teacher-card"><div class="sidebar-teacher-name">' + t.name + '</div><div class="sidebar-teacher-badge badge-lecture">' + t.hours + ' soat</div></div>';
                        } else {
                            lectureEl.innerHTML = '<div class="sidebar-teacher-card"><div class="sidebar-teacher-name" style="color:#9ca3af;">-</div></div>';
                        }
                    }
                    if (practiceEl) {
                        if (data.teacher_data && data.teacher_data.practice_teachers && data.teacher_data.practice_teachers.length > 0) {
                            practiceEl.innerHTML = data.teacher_data.practice_teachers
                                .map(t => '<div class="sidebar-teacher-card"><div class="sidebar-teacher-name">' + t.name + '</div><div class="sidebar-teacher-badge badge-practice">' + t.hours + ' soat</div></div>')
                                .join('');
                        } else {
                            practiceEl.innerHTML = '<div class="sidebar-teacher-card"><div class="sidebar-teacher-name" style="color:#9ca3af;">-</div></div>';
                        }
                    }
                    // Kafedra ma'lumot sifatida yangilanadi
                    const kafedraEl = document.getElementById('kafedra-display');
                    if (kafedraEl) kafedraEl.textContent = data.kafedra_name || '';
                    fields.forEach(f => setLoading(f, false));
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        fields.forEach(f => setLoading(f, false));
                    }
                });
        }

        // ===== Kaskad filtr handlerlari =====

        // Fakultet o'zgardi → yo'nalish, kurs, semestr, guruh, fan tozalanadi
        function onFacultyChange() {
            cascadeReset('faculty');
            refreshFilters();
        }

        // Yo'nalish o'zgardi → kurs, semestr, guruh, fan tozalanadi
        function onSpecialtyChange() {
            cascadeReset('specialty');
            refreshFilters();
        }

        // Kurs o'zgardi → semestr, guruh, fan tozalanadi
        function onLevelChange() {
            cascadeReset('level');
            refreshFilters();
        }

        // Semestr o'zgardi → guruh, fan tozalanadi va yangilanadi
        function onSemesterChange() {
            cascadeReset('semester');
            refreshFilters();
        }

        // ===== Navigatsiya filtrlari =====

        // Guruh o'zgardi → mos fan topiladi → navigatsiya
        function onGroupChange(newGroupId) {
            if (!newGroupId) return;
            const values = getFilterValues();
            values.group_id = newGroupId;

            setLoading('subject', true);
            fetch(`${sidebarOptionsUrl}?${buildQS(values)}`)
                .then(r => r.json())
                .then(data => {
                    setLoading('subject', false);
                    if (!data.subjects || Object.keys(data.subjects).length === 0) {
                        alert('Bu guruh uchun fanlar topilmadi');
                        return;
                    }
                    let targetSubject = values.subject_id;
                    if (!data.subjects[targetSubject]) {
                        targetSubject = Object.keys(data.subjects)[0];
                    }
                    let targetSemester = values.semester_code;
                    if (!targetSemester || (data.semesters && !data.semesters[targetSemester])) {
                        targetSemester = data.semesters ? Object.keys(data.semesters)[0] : '';
                    }
                    if (targetSubject && targetSemester) {
                        navigateToJournal(newGroupId, targetSubject, targetSemester);
                    }
                })
                .catch(() => {
                    setLoading('subject', false);
                    alert('Xatolik yuz berdi');
                });
        }

        // Fan o'zgardi → mos guruh topiladi → navigatsiya
        function onSubjectChange(newSubjectId) {
            if (!newSubjectId) return;
            const values = getFilterValues();
            values.subject_id = newSubjectId;

            setLoading('group', true);
            fetch(`${sidebarOptionsUrl}?${buildQS(values)}`)
                .then(r => r.json())
                .then(data => {
                    setLoading('group', false);
                    if (!data.groups || Object.keys(data.groups).length === 0) {
                        alert('Bu fan uchun guruhlar topilmadi');
                        return;
                    }
                    let targetGroup = values.group_id;
                    if (!data.groups[targetGroup]) {
                        targetGroup = Object.keys(data.groups)[0];
                    }
                    let targetSemester = values.semester_code;
                    if (!targetSemester || (data.semesters && !data.semesters[targetSemester])) {
                        targetSemester = data.semesters ? Object.keys(data.semesters)[0] : '';
                    }
                    if (targetGroup && targetSemester) {
                        navigateToJournal(targetGroup, newSubjectId, targetSemester);
                    }
                })
                .catch(() => {
                    setLoading('group', false);
                    alert('Xatolik yuz berdi');
                });
        }

        // ===== Mavzular =====
        function loadTopics() {
            const body = document.getElementById('mavzular-body');
            if (!body) return;

            const params = new URLSearchParams({
                semester_id: currentSemesterHemisId,
                curriculum_id: currentCurriculumHemisId,
                subject_id: currentSubjectId,
                limit: 200,
            });

            body.innerHTML = '<div class="mavzular-loading"><div class="sidebar-spinner" style="margin:0 auto 8px;"></div>Mavzular yuklanmoqda...</div>';

            fetch(`${topicsUrl}?${params}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.data || !data.data.items || data.data.items.length === 0) {
                        body.innerHTML = '<div class="mavzular-empty">Mavzular topilmadi</div>';
                        return;
                    }

                    const items = data.data.items.sort((a, b) => (a.position || 0) - (b.position || 0));

                    let html = '<table class="mavzular-table"><thead><tr>' +
                        '<th style="text-align:center;width:36px;">#</th>' +
                        '<th style="text-align:center;width:60px;">Soat</th>' +
                        '<th>Mavzu nomi</th>' +
                        '<th style="width:90px;">Sana</th>' +
                        '</tr></thead><tbody>';

                    items.forEach((item, i) => {
                        const date = item.created_at ? new Date(item.created_at * 1000).toLocaleDateString('uz-UZ', {year:'numeric', month:'2-digit', day:'2-digit'}) : '-';
                        html += '<tr>' +
                            '<td class="topic-num">' + (i + 1) + '</td>' +
                            '<td class="topic-hours"><span class="topic-hours-badge">' + (item.topic_load || 0) + '</span></td>' +
                            '<td class="topic-name">' + (item.name || '-') + '</td>' +
                            '<td class="topic-date">' + date + '</td>' +
                            '</tr>';
                    });

                    html += '</tbody></table>';
                    body.innerHTML = html;
                })
                .catch(err => {
                    body.innerHTML = '<div class="mavzular-error">Xatolik: ' + err.message + '</div>';
                });
        }

        // Sahifa yuklanganda filtrlarni to'ldirish
        document.addEventListener('DOMContentLoaded', function() {
            refreshFilters({
                faculty_id: urlParams.get('faculty_id') || currentFacultyId,
                specialty_id: urlParams.get('specialty_id') || currentSpecialtyId,
                level_code: urlParams.get('level_code') || currentLevelCode,
                semester_code: currentSemesterCode,
                group_id: currentGroupId,
                subject_id: currentSubjectId,
            });
            loadTopics();
        });

        // MT Grade save configuration
        const mtGradeConfig = {
            url: '{{ route("admin.journal.save-mt-grade") }}',
            subjectId: '{{ $subjectId }}',
            semesterCode: '{{ $semesterCode }}',
            csrfToken: '{{ csrf_token() }}'
        };

        const isDekan = {{ $isDekan ? 'true' : 'false' }};
        const historyDownloadBase = '{{ url("admin/journal/download-history-file") }}/';

        function updateMtHistoryCell(studentHemisId, history) {
            const cell = document.getElementById('mt-history-' + studentHemisId);
            if (!cell || !history || history.length === 0) return;
            let html = '';
            history.forEach(h => {
                const cls = h.grade >= 60 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                let fileLink = '';
                if (h.has_file && h.id) {
                    fileLink = ' <a href="' + historyDownloadBase + h.id + '" class="ml-0.5 hover:text-blue-700" title="' + h.attempt + '-urinish fayli">&#128206;</a>';
                }
                html += '<span class="inline-flex items-center px-1.5 py-0.5 text-xs rounded ' + cls + ' mr-0.5">' +
                        h.attempt + ': ' + Math.round(h.grade) + fileLink + '</span>';
            });
            cell.innerHTML = html;
        }

        function updateMtActionCell(studentHemisId, data) {
            const cell = document.getElementById('mt-action-' + studentHemisId);
            if (!cell) return;

            const grade = parseFloat(data.grade);
            if (grade >= 60) {
                cell.innerHTML = '<span style="display:inline-flex;align-items:center;padding:4px 10px;font-size:12px;background:#dcfce7;color:#15803d;border-radius:6px;">&#128274; Qabul qilindi</span>';
            } else if (data.can_regrade && !isDekan) {
                cell.innerHTML = '<button type="button" onclick="startRegrade(\'' + studentHemisId + '\')" ' +
                    'style="padding:6px 16px;font-size:13px;font-weight:600;background:#f97316;color:#fff;border:none;border-radius:6px;cursor:pointer;">' +
                    'Qayta baholash</button>';
            } else if (data.waiting_resubmit) {
                // Grade < 60, talaba qayta yuklashi kerak
                cell.innerHTML = '<span style="display:inline-flex;align-items:center;padding:4px 10px;font-size:12px;background:#fef9c3;color:#92400e;border-radius:6px;">&#128274; Kutilmoqda</span>';
            } else {
                cell.innerHTML = '<span style="display:inline-flex;align-items:center;padding:4px 10px;font-size:12px;background:#fee2e2;color:#b91c1c;border-radius:6px;">Limit tugagan</span>';
            }
        }

        function saveMtGrade(studentHemisId, isRegrade) {
            if (isDekan) return;
            const input = document.getElementById('mt-grade-' + studentHemisId);
            const grade = input.value;

            if (grade === '' || isNaN(grade) || grade < 0 || grade > 100) {
                alert('Iltimos, 0 dan 100 gacha baho kiriting');
                return;
            }

            // Disable input while saving
            const actionCell = document.getElementById('mt-action-' + studentHemisId);
            const buttons = actionCell.querySelectorAll('button');
            buttons.forEach(b => { b.textContent = '...'; b.disabled = true; });

            fetch(mtGradeConfig.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': mtGradeConfig.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    student_hemis_id: studentHemisId,
                    subject_id: mtGradeConfig.subjectId,
                    semester_code: mtGradeConfig.semesterCode,
                    grade: parseFloat(grade),
                    regrade: isRegrade ? true : false
                })
            })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (data.success) {
                    // Lock the input
                    input.value = Math.round(data.grade);
                    input.disabled = true;
                    input.style.background = '#f3f4f6';
                    input.style.color = '#6b7280';
                    // Update history
                    if (data.history) {
                        updateMtHistoryCell(studentHemisId, data.history);
                    }
                    // Update action cell
                    updateMtActionCell(studentHemisId, data);
                    // Update MT badge count
                    updateMtBadge();
                    // Update MT% cells in Amaliyot tab
                    updateMtPercentCells(studentHemisId, data.grade);
                } else if (data.locked && !data.can_regrade) {
                    // Permanently locked
                    input.value = Math.round(data.grade);
                    input.disabled = true;
                    input.style.background = '#f3f4f6';
                    input.style.color = '#6b7280';
                    updateMtActionCell(studentHemisId, data);
                    alert(data.message);
                } else if (data.locked && data.can_regrade) {
                    // Already graded, can regrade
                    input.value = Math.round(data.grade);
                    input.disabled = true;
                    input.style.background = '#f3f4f6';
                    input.style.color = '#6b7280';
                    updateMtActionCell(studentHemisId, data);
                } else if (data.no_file) {
                    // Student has no file uploaded
                    alert(data.message);
                    input.disabled = true;
                    input.style.background = '#f3f4f6';
                    input.style.color = '#6b7280';
                    const actionCell = document.getElementById('mt-action-' + studentHemisId);
                    actionCell.innerHTML = '<span style="color:#9ca3af;font-size:12px;">—</span>';
                } else {
                    alert('Xatolik: ' + (data.message || 'Baho saqlanmadi'));
                    buttons.forEach(b => { b.textContent = 'Saqlash'; b.disabled = false; });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                buttons.forEach(b => { b.textContent = 'Saqlash'; b.disabled = false; });
            });
        }

        function startRegrade(studentHemisId) {
            if (isDekan) return;
            const input = document.getElementById('mt-grade-' + studentHemisId);
            const actionCell = document.getElementById('mt-action-' + studentHemisId);

            // Unlock input for new grade
            input.disabled = false;
            input.style.background = '#fff';
            input.style.color = '#111827';
            input.value = '';
            input.focus();

            // Show save button for regrade
            actionCell.innerHTML =
                '<button type="button" onclick="saveMtGrade(\'' + studentHemisId + '\', true)" ' +
                'style="padding:6px 14px;font-size:13px;font-weight:600;background:#16a34a;color:#fff;border:none;border-radius:6px;cursor:pointer;margin-right:4px;">Saqlash</button>' +
                '<button type="button" onclick="cancelRegrade(\'' + studentHemisId + '\')" ' +
                'style="padding:6px 14px;font-size:13px;font-weight:600;background:#9ca3af;color:#fff;border:none;border-radius:6px;cursor:pointer;">Bekor</button>';
        }

        function cancelRegrade(studentHemisId) {
            // Reload the page to restore original state
            location.reload();
        }

        // Update MT tab badge count after grading
        function updateMtBadge() {
            var badge = document.getElementById('tab-mustaqil')?.querySelector('span');
            if (!badge) return;
            var count = parseInt(badge.textContent) || 0;
            if (count > 1) {
                badge.textContent = count - 1;
            } else {
                badge.remove();
            }
        }

        // Update MT% cells across all tabs (Amaliyot, MT) after save
        function updateMtPercentCells(studentHemisId, grade) {
            var cells = document.querySelectorAll('.mt-cell-' + studentHemisId);
            cells.forEach(function(cell) {
                var span = cell.querySelector('span.font-bold');
                if (span) {
                    span.textContent = Math.round(grade);
                    span.style.color = grade < 60 ? '#dc2626' : '#2563eb';
                }
            });
        }

        // MT grading urgency modal
        const mtDangerCount = {{ $dangerCount ?? 0 }};
        const mtUngradedCount = {{ $ungradedCount ?? 0 }};
        let mtModalShown = false;

        function showMtUrgencyModal() {
            if (mtModalShown || mtDangerCount === 0) return;
            mtModalShown = true;

            const overlay = document.createElement('div');
            overlay.id = 'mt-urgency-modal';
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            overlay.innerHTML = `
                <div class="bg-white rounded-lg shadow-xl max-w-md mx-4 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-4xl text-red-500 animate-pulse">&#9888;</span>
                        <h3 class="text-lg font-bold text-red-700">Diqqat! Baholanmagan topshiriqlar</h3>
                    </div>
                    <p class="text-sm text-gray-700 mb-2">
                        <strong>${mtDangerCount}</strong> ta topshiriq <strong>3 kundan oshiq</strong> baholanmagan!
                    </p>
                    <p class="text-sm text-gray-500 mb-4">
                        Jami baholanmagan: ${mtUngradedCount} ta. Iltimos, tezroq baholang.
                    </p>
                    <div class="flex gap-2 justify-end">
                        <button onclick="document.getElementById('mt-urgency-modal').remove()"
                            class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            Keyinroq
                        </button>
                        <button onclick="document.getElementById('mt-urgency-modal').remove()"
                            class="px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 font-medium">
                            Baholashni boshlash
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        // Sidebar toggle (1024px dan past)
        function toggleMobileSidebar() {
            if (window.innerWidth > 1024) return;
            document.getElementById('journalSidebar').classList.toggle('collapsed');
        }
        // Mobilda sahifa ochilganda sidebar yig'iq, desktopga o'tsa ochiq
        (function() {
            var sb = document.getElementById('journalSidebar');
            if (window.innerWidth <= 1024) sb.classList.add('collapsed');
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024) sb.classList.remove('collapsed');
                else if (!sb.classList.contains('collapsed')) sb.classList.add('collapsed');
            });
        })();

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('content-' + tabName).classList.remove('hidden');
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.add('active');

            // Show urgency modal when MT tab is opened
            if (tabName === 'mustaqil') {
                showMtUrgencyModal();
            }
        }

        function switchView(viewType) {
            document.querySelectorAll('.sidebar-view-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('view-' + viewType).classList.add('active');

            if (viewType === 'compact') {
                document.getElementById('mz-compact-view')?.classList.remove('hidden');
                document.getElementById('mz-detailed-view')?.classList.add('hidden');
                document.getElementById('jb-compact-view')?.classList.remove('hidden');
                document.getElementById('jb-detailed-view')?.classList.add('hidden');
                document.getElementById('mt-compact-view')?.classList.remove('hidden');
                document.getElementById('mt-detailed-view')?.classList.add('hidden');
            } else {
                document.getElementById('mz-compact-view')?.classList.add('hidden');
                document.getElementById('mz-detailed-view')?.classList.remove('hidden');
                document.getElementById('jb-compact-view')?.classList.add('hidden');
                document.getElementById('jb-detailed-view')?.classList.remove('hidden');
                document.getElementById('mt-compact-view')?.classList.add('hidden');
                document.getElementById('mt-detailed-view')?.classList.remove('hidden');
            }
        }

        // Retake grade functionality - Excel-like inline editing
        let currentEditingCell = null;

        function makeEditable(cellDiv, gradeId) {
            if (isDekan) return;
            // Prevent multiple edits at once
            if (currentEditingCell) {
                return;
            }

            currentEditingCell = cellDiv;
            const originalContent = cellDiv.innerHTML;

            // Create input field
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.max = '100';
            input.value = '';
            input.className = 'w-full text-center border border-blue-500 rounded px-1 py-0.5 focus:outline-none focus:ring-2 focus:ring-blue-300';
            input.style.width = '50px';
            input.style.height = '28px';

            // Replace cell content with input
            cellDiv.innerHTML = '';
            cellDiv.appendChild(input);
            input.focus();
            input.select();

            // Save on Enter key
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveInlineGrade(gradeId, input.value, cellDiv, originalContent);
                } else if (e.key === 'Escape') {
                    // Cancel editing
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });

            // Save on blur (clicking outside)
            input.addEventListener('blur', function() {
                if (input.value.trim() !== '') {
                    saveInlineGrade(gradeId, input.value, cellDiv, originalContent);
                } else {
                    // Cancel if empty
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });
        }

        function makeEditableEmpty(cellDiv, studentHemisId, lessonDate, lessonPair, subjectId, semesterCode) {
            if (isDekan) return;
            // Prevent multiple edits at once
            if (currentEditingCell) {
                return;
            }

            currentEditingCell = cellDiv;
            const originalContent = cellDiv.innerHTML;

            // Create input field
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.max = '100';
            input.value = '';
            input.className = 'w-full text-center border border-blue-500 rounded px-1 py-0.5 focus:outline-none focus:ring-2 focus:ring-blue-300';
            input.style.width = '50px';
            input.style.height = '28px';

            // Replace cell content with input
            cellDiv.innerHTML = '';
            cellDiv.appendChild(input);
            input.focus();
            input.select();

            // Save on Enter key
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEmptyGrade(studentHemisId, lessonDate, lessonPair, subjectId, semesterCode, input.value, cellDiv, originalContent);
                } else if (e.key === 'Escape') {
                    // Cancel editing
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });

            // Save on blur (clicking outside)
            input.addEventListener('blur', function() {
                if (input.value.trim() !== '') {
                    saveEmptyGrade(studentHemisId, lessonDate, lessonPair, subjectId, semesterCode, input.value, cellDiv, originalContent);
                } else {
                    // Cancel if empty
                    cellDiv.innerHTML = originalContent;
                    currentEditingCell = null;
                }
            });
        }

        function saveEmptyGrade(studentHemisId, lessonDate, lessonPair, subjectId, semesterCode, gradeValue, cellDiv, originalContent) {
            const gradeNum = parseFloat(gradeValue);

            if (isNaN(gradeNum) || gradeNum < 0 || gradeNum > 100) {
                alert('Iltimos, 0 dan 100 gacha baho kiriting');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
                return;
            }

            // Show loading
            cellDiv.innerHTML = '<span class="text-gray-500">...</span>';

            fetch('{{ route("admin.journal.create-retake-grade") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    student_hemis_id: studentHemisId,
                    lesson_date: lessonDate,
                    lesson_pair_code: lessonPair,
                    subject_id: subjectId,
                    semester_code: semesterCode,
                    grade: gradeNum
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Bo'sh katak: oddiy baho sifatida ko'rsatish
                    const gradeVal = Math.round(data.grade);
                    cellDiv.innerHTML = `<span class="text-gray-900 font-medium">${gradeVal}</span>`;

                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
                    notification.textContent = `Saqlandi: ${gradeVal}`;
                    document.body.appendChild(notification);

                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                } else {
                    alert('Xatolik: ' + (data.message || 'Baho saqlanmadi'));
                    cellDiv.innerHTML = originalContent;
                }
                currentEditingCell = null;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
            });
        }

        function saveInlineGrade(gradeId, gradeValue, cellDiv, originalContent) {
            const gradeNum = parseFloat(gradeValue);

            if (isNaN(gradeNum) || gradeNum < 0 || gradeNum > 100) {
                alert('Iltimos, 0 dan 100 gacha baho kiriting');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
                return;
            }

            // Show loading
            cellDiv.innerHTML = '<span class="text-gray-500">...</span>';

            fetch('{{ route("admin.journal.save-retake-grade") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    grade_id: gradeId,
                    grade: gradeNum
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const retakeVal = Math.round(data.retake_grade);
                    // Diagonal split cell for NB and low grade retakes
                    if (data.reason === 'absent') {
                        // NB otrabotka: NB yuqorida, retake pastda
                        const nbColor = data.is_excused ? 'color:#16a34a' : 'color:#dc2626';
                        const nbTitle = data.is_excused ? 'sababli' : 'sababsiz';
                        cellDiv.innerHTML = `<div class="split-cell" title="NB (${nbTitle}), Otrabotka: ${retakeVal}">
                            <svg class="split-line" viewBox="0 0 100 100" preserveAspectRatio="none"><line x1="0" y1="100" x2="100" y2="0" /></svg>
                            <span class="split-top" style="${nbColor};font-size:10px;">NB</span>
                            <span class="split-bottom">${retakeVal}</span>
                        </div>`;
                    } else if (data.reason === 'low_grade' && data.original_grade !== null) {
                        // Past baho otrabotka: eski baho yuqorida (qizil), retake pastda
                        const origVal = Math.round(data.original_grade);
                        cellDiv.innerHTML = `<div class="split-cell" title="Oldingi: ${origVal}, Otrabotka: ${retakeVal}">
                            <svg class="split-line" viewBox="0 0 100 100" preserveAspectRatio="none"><line x1="0" y1="100" x2="100" y2="0" /></svg>
                            <span class="split-top" style="color:#dc2626;">${origVal}</span>
                            <span class="split-bottom">${retakeVal}</span>
                        </div>`;
                    } else {
                        // Boshqa holatlar: checkmark
                        cellDiv.innerHTML = `<div class="flex items-center justify-center gap-1">
                            <span class="grade-retake font-medium">${retakeVal}</span>
                            <span class="text-green-600 text-xs" title="Baho qo'yilgan: ${data.percentage}%">✓</span>
                        </div>`;
                    }

                    // Show success notification briefly
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
                    notification.textContent = `Saqlandi: ${retakeVal} (${data.percentage}%)`;
                    document.body.appendChild(notification);

                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                } else {
                    alert('Xatolik: ' + (data.message || 'Baho saqlanmadi'));
                    cellDiv.innerHTML = originalContent;
                }
                currentEditingCell = null;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                cellDiv.innerHTML = originalContent;
                currentEditingCell = null;
            });
        }
    </script>

    {{-- ===== DARS OCHISH MODAL ===== --}}
    @if($canOpenLesson ?? false)
    <div id="lessonOpenModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:28px; max-width:460px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                <div style="width:44px; height:44px; background:linear-gradient(135deg,#f59e0b,#d97706); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <svg width="22" height="22" style="color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                </div>
                <div>
                    <div style="font-size:17px; font-weight:700; color:#1e293b;">Dars ochish</div>
                    <div style="font-size:13px; color:#64748b;" id="lessonOpenDateLabel">Sana: —</div>
                </div>
            </div>

            <form id="lessonOpenForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="group_hemis_id" value="{{ $group->group_hemis_id }}">
                <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                <input type="hidden" name="semester_code" value="{{ $semesterCode }}">
                <input type="hidden" name="lesson_date" id="lessonOpenDate" value="">

                <div style="margin-bottom:16px;">
                    <label style="font-size:13px; font-weight:600; color:#374151; display:block; margin-bottom:6px;">O'qituvchi bildirgisi (fayl) <span style="color:#ef4444;">*</span></label>
                    <input type="file" name="file" id="lessonOpenFile" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.heic"
                        style="width:100%; padding:10px; border:2px dashed #d1d5db; border-radius:10px; font-size:13px; color:#374151; background:#f9fafb; cursor:pointer;">
                    <div style="font-size:11px; color:#9ca3af; margin-top:4px;">PDF, DOC, DOCX, JPG, PNG — max 10MB</div>
                </div>

                <div style="background:#eff6ff; border-radius:10px; padding:12px 14px; margin-bottom:18px; border:1px solid #bfdbfe;">
                    <div style="font-size:12px; color:#1e40af; line-height:1.6;">
                        Dars ochilgach o'qituvchiga <b>{{ $lessonOpeningDays ?? 3 }} kun</b> (soat 23:59 gacha) baho qo'yish imkoniyati beriladi.
                    </div>
                </div>

                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="closeLessonModal()" style="padding:10px 20px; background:#f1f5f9; color:#475569; font-size:14px; font-weight:600; border-radius:10px; border:1px solid #e2e8f0; cursor:pointer;">Bekor qilish</button>
                    <button type="submit" id="lessonOpenSubmit" style="padding:10px 24px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; font-size:14px; font-weight:600; border-radius:10px; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(245,158,11,0.3);">Dars ochish</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Dars ochilishi haqida ma'lumot modali --}}
    <div id="opening-info-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.3); max-width:420px; width:90%; margin:auto; overflow:hidden;">
            <div id="oim-header" style="padding:14px 20px; color:#fff; display:flex; align-items:center; justify-content:space-between;">
                <span style="font-weight:700; font-size:15px;" id="oim-title"></span>
                <button onclick="document.getElementById('opening-info-modal').style.display='none'" style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <div style="padding:16px 20px;">
                <table style="width:100%; font-size:13px; border-collapse:collapse;">
                    <tr>
                        <td style="padding:6px 0; color:#6b7280; width:140px;">Dars ochilgan:</td>
                        <td style="padding:6px 0; font-weight:600;" id="oim-opened-at"></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#6b7280;">Kim ochgan:</td>
                        <td style="padding:6px 0; font-weight:600;" id="oim-opened-by"></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#6b7280;">Muddat:</td>
                        <td style="padding:6px 0; font-weight:600;" id="oim-deadline"></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#6b7280;">Holat:</td>
                        <td style="padding:6px 0;" id="oim-status"></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#6b7280;">Baholar soni:</td>
                        <td style="padding:6px 0; font-weight:600;" id="oim-grade-count"></td>
                    </tr>
                    <tr id="oim-grade-row" style="display:none;">
                        <td style="padding:6px 0; color:#6b7280;">Oxirgi baho:</td>
                        <td style="padding:6px 0; font-weight:600;" id="oim-last-grade"></td>
                    </tr>
                </table>
                <div style="margin-top:14px; padding-top:14px; border-top:1px solid #e5e7eb; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:18px;">&#128206;</span>
                    <a id="oim-file-link" href="#" target="_blank" style="color:#2563eb; font-size:13px; font-weight:600; text-decoration:none; word-break:break-all;"></a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showOpeningInfo(data) {
            const modal = document.getElementById('opening-info-modal');
            const header = document.getElementById('oim-header');
            const isActive = data.status === 'active';

            document.getElementById('oim-title').textContent = 'Dars: ' + data.date;
            header.style.background = isActive ? '#10b981' : '#6b7280';
            document.getElementById('oim-opened-at').textContent = data.opened_at;
            document.getElementById('oim-opened-by').textContent = data.opened_by;
            document.getElementById('oim-deadline').textContent = data.deadline;

            const statusEl = document.getElementById('oim-status');
            if (isActive) {
                statusEl.innerHTML = '<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600;">Faol</span>';
            } else if (data.status === 'expired') {
                statusEl.innerHTML = '<span style="background:#fef2f2; color:#991b1b; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600;">Muddati tugagan</span>';
            } else {
                statusEl.innerHTML = '<span style="background:#f3f4f6; color:#374151; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600;">Yopilgan</span>';
            }

            document.getElementById('oim-grade-count').textContent = data.grade_count + ' ta';

            const gradeRow = document.getElementById('oim-grade-row');
            if (data.last_grade_at) {
                gradeRow.style.display = '';
                document.getElementById('oim-last-grade').textContent = data.last_grade_at;
            } else {
                gradeRow.style.display = 'none';
            }

            const fileLink = document.getElementById('oim-file-link');
            fileLink.href = data.file_url;
            fileLink.textContent = data.file_name || 'Faylni ko\'rish';

            modal.style.display = 'flex';
        }

        document.getElementById('opening-info-modal').addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    </script>

    <script>
        function openLessonModal(dateStr) {
            document.getElementById('lessonOpenDate').value = dateStr;
            document.getElementById('lessonOpenDateLabel').textContent = 'Sana: ' + dateStr;
            document.getElementById('lessonOpenFile').value = '';
            document.getElementById('lessonOpenModal').style.display = 'flex';
        }

        function closeLessonModal() {
            document.getElementById('lessonOpenModal').style.display = 'none';
        }

        document.getElementById('lessonOpenForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('lessonOpenSubmit');
            btn.disabled = true;
            btn.textContent = 'Yuklanmoqda...';

            const formData = new FormData(this);

            fetch('{{ route("admin.journal.open-lesson") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeLessonModal();
                    // Sahifani qayta yuklash
                    window.location.reload();
                } else {
                    alert('Xatolik: ' + (data.message || 'Dars ochilmadi'));
                    btn.disabled = false;
                    btn.textContent = 'Dars ochish';
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Xatolik yuz berdi');
                btn.disabled = false;
                btn.textContent = 'Dars ochish';
            });
        });

        // Modal tashqarisiga bosilsa yopish
        document.getElementById('lessonOpenModal').addEventListener('click', function(e) {
            if (e.target === this) closeLessonModal();
        });
    </script>
    @endif

    {{-- ===== OCHILGAN DARSGA BAHO QO'YISH (O'QITUVCHI) - EXCEL-STYLE ===== --}}
    <script>
        // ===== PENDING GRADES STORE =====
        let pendingOpenedGrades = {};

        function getGradeKey(cellDiv) {
            return `${cellDiv.dataset.student}_${cellDiv.dataset.date}_${cellDiv.dataset.pair}`;
        }

        function startEditOpened(cellDiv) {
            // Agar allaqachon input bo'lsa, qayta yaratmaslik
            if (cellDiv.querySelector('input')) return;

            const key = getGradeKey(cellDiv);
            const pending = pendingOpenedGrades[key];
            const currentSpan = cellDiv.querySelector('span');
            const currentVal = currentSpan ? currentSpan.textContent.trim() : '';
            const numVal = pending ? String(pending.grade) : (currentVal === '-' ? '' : currentVal);

            // Input yaratish
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.max = '100';
            input.step = '1';
            input.value = numVal;
            input.style.cssText = 'width:48px; padding:2px 4px; text-align:center; font-size:12px; border:2px solid #10b981; border-radius:4px; outline:none; background:#f0fdf4;';

            cellDiv.innerHTML = '';
            cellDiv.appendChild(input);
            input.focus();
            input.select();

            // Keyboard navigation
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    storePendingGrade(cellDiv, input.value);
                    moveToCell(cellDiv, 'down');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    storePendingGrade(cellDiv, input.value);
                    moveToCell(cellDiv, 'up');
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    storePendingGrade(cellDiv, input.value);
                    moveToCell(cellDiv, e.shiftKey ? 'left' : 'right');
                } else if (e.key === 'Escape') {
                    // Bekor qilish — pending bo'lsa ko'rsatish, bo'lmasa dash
                    if (pending) {
                        showPendingInCell(cellDiv, pending.grade);
                    } else {
                        cellDiv.innerHTML = '<span class="text-green-400">-</span>';
                        cellDiv.style.background = '#f0fdf4';
                    }
                }
            });

            // Blur — tashqariga bosilsa saqlash
            input.addEventListener('blur', function() {
                // Timeout — keyingi cell ga o'tishda blur bo'lmasligi uchun
                setTimeout(() => {
                    if (!cellDiv.querySelector('input')) return;
                    storePendingGrade(cellDiv, input.value);
                }, 150);
            });
        }

        function storePendingGrade(cellDiv, value) {
            const key = getGradeKey(cellDiv);
            const grade = parseInt(value);

            if (value.trim() === '' || isNaN(grade) || grade < 0 || grade > 100) {
                // Bo'sh yoki noto'g'ri — pending dan o'chirish
                if (pendingOpenedGrades[key]) {
                    delete pendingOpenedGrades[key];
                }
                cellDiv.innerHTML = '<span class="text-green-400">-</span>';
                cellDiv.style.background = '#f0fdf4';
                updatePendingPanel();
                return;
            }

            // Pending ga qo'shish
            pendingOpenedGrades[key] = {
                student_hemis_id: cellDiv.dataset.student,
                lesson_date: cellDiv.dataset.date,
                lesson_pair_code: cellDiv.dataset.pair,
                subject_id: cellDiv.dataset.subject,
                semester_code: cellDiv.dataset.semester,
                group_hemis_id: cellDiv.dataset.group,
                grade: grade,
                cellDiv: cellDiv
            };

            showPendingInCell(cellDiv, grade);
            updatePendingPanel();
        }

        function showPendingInCell(cellDiv, grade) {
            const color = grade < 60 ? 'color:#dc2626' : 'color:#111827';
            cellDiv.innerHTML = `<span class="font-medium" style="${color}">${grade}</span>`;
            cellDiv.style.background = '#fef9c3'; // Sariq — pending
        }

        function moveToCell(currentCell, direction) {
            const allCells = Array.from(document.querySelectorAll('.grade-cell-opened'));
            if (allCells.length === 0) return;

            const currentRow = parseInt(currentCell.dataset.row);
            const currentCol = parseInt(currentCell.dataset.col);
            let targetCell = null;

            if (direction === 'down') {
                // Keyingi qator, o'sha ustun
                let minRowDiff = Infinity;
                allCells.forEach(c => {
                    const r = parseInt(c.dataset.row);
                    const col = parseInt(c.dataset.col);
                    if (col === currentCol && r > currentRow && (r - currentRow) < minRowDiff) {
                        minRowDiff = r - currentRow;
                        targetCell = c;
                    }
                });
            } else if (direction === 'up') {
                // Oldingi qator, o'sha ustun
                let minRowDiff = Infinity;
                allCells.forEach(c => {
                    const r = parseInt(c.dataset.row);
                    const col = parseInt(c.dataset.col);
                    if (col === currentCol && r < currentRow && (currentRow - r) < minRowDiff) {
                        minRowDiff = currentRow - r;
                        targetCell = c;
                    }
                });
            } else if (direction === 'right') {
                // O'sha qator, keyingi ustun
                let minColDiff = Infinity;
                allCells.forEach(c => {
                    const r = parseInt(c.dataset.row);
                    const col = parseInt(c.dataset.col);
                    if (r === currentRow && col > currentCol && (col - currentCol) < minColDiff) {
                        minColDiff = col - currentCol;
                        targetCell = c;
                    }
                });
            } else if (direction === 'left') {
                // O'sha qator, oldingi ustun
                let minColDiff = Infinity;
                allCells.forEach(c => {
                    const r = parseInt(c.dataset.row);
                    const col = parseInt(c.dataset.col);
                    if (r === currentRow && col < currentCol && (currentCol - col) < minColDiff) {
                        minColDiff = currentCol - col;
                        targetCell = c;
                    }
                });
            }

            if (targetCell) {
                startEditOpened(targetCell);
            }
        }

        function createPendingPanel() {
            var panel = document.createElement('div');
            panel.id = 'pending-save-panel';
            panel.setAttribute('style', 'display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:99999; background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; padding:12px 24px; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,0.25); display:flex; align-items:center; gap:16px; font-size:14px; font-weight:600;');
            panel.style.display = 'none';

            var infoDiv = document.createElement('div');
            infoDiv.setAttribute('style', 'display:flex; align-items:center; gap:8px;');
            infoDiv.innerHTML = '<span>Saqlanmagan baholar: <b id="pending-count">0</b> ta</span>';
            panel.appendChild(infoDiv);

            var btnDiv = document.createElement('div');
            btnDiv.setAttribute('style', 'display:flex; gap:8px; margin-left:8px;');

            var saveBtn = document.createElement('button');
            saveBtn.id = 'save-all-btn';
            saveBtn.textContent = 'Saqlash';
            saveBtn.setAttribute('style', 'background:#fff; color:#d97706; border:none; padding:8px 20px; border-radius:10px; font-weight:700; font-size:14px; cursor:pointer; box-shadow:0 2px 8px rgba(0,0,0,0.15);');
            saveBtn.addEventListener('click', function() { saveAllPendingGrades(); });
            btnDiv.appendChild(saveBtn);

            panel.appendChild(btnDiv);
            document.body.appendChild(panel);
            return panel;
        }

        function updatePendingPanel() {
            var count = Object.keys(pendingOpenedGrades).length;
            var panel = document.getElementById('pending-save-panel');
            if (!panel) {
                panel = createPendingPanel();
            }
            if (count > 0) {
                panel.style.display = 'flex';
                var countEl = document.getElementById('pending-count');
                if (countEl) countEl.textContent = count;
            } else {
                panel.style.display = 'none';
            }
        }

        function saveAllPendingGrades() {
            const grades = Object.values(pendingOpenedGrades);
            if (grades.length === 0) return;

            const btn = document.getElementById('save-all-btn');
            btn.disabled = true;
            btn.textContent = 'Saqlanmoqda...';
            btn.style.opacity = '0.7';

            const saveUrl = '{{ route("admin.journal.save-opened-lesson-grade") }}';
            const csrfToken = '{{ csrf_token() }}';

            const promises = grades.map(g => {
                return fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        student_hemis_id: g.student_hemis_id,
                        lesson_date: g.lesson_date,
                        lesson_pair_code: g.lesson_pair_code,
                        subject_id: g.subject_id,
                        semester_code: g.semester_code,
                        group_hemis_id: g.group_hemis_id,
                        grade: g.grade
                    })
                })
                .then(r => {
                    if (!r.ok) {
                        return r.text().then(text => {
                            try { return { data: JSON.parse(text), gradeInfo: g }; }
                            catch(e) { return { error: new Error('HTTP ' + r.status), gradeInfo: g }; }
                        });
                    }
                    return r.json().then(data => ({ data, gradeInfo: g }));
                })
                .catch(err => ({ error: err, gradeInfo: g }));
            });

            Promise.all(promises).then(results => {
                let successCount = 0;
                let errorCount = 0;
                let firstErrMsg = '';

                results.forEach(({ data, error, gradeInfo }) => {
                    const cellDiv = gradeInfo.cellDiv;
                    const key = getGradeKey(cellDiv);

                    if (error || !data?.success) {
                        errorCount++;
                        const msg = data?.message || error?.message || 'Saqlanmadi';
                        if (!firstErrMsg) firstErrMsg = msg;
                        cellDiv.title = 'Xatolik: ' + msg;
                        cellDiv.style.background = '#fecaca';
                        console.error('Grade save error:', msg, gradeInfo);
                    } else {
                        successCount++;
                        const gradeVal = Math.round(data.grade);
                        const color = gradeVal < 60 ? 'color:#dc2626' : 'color:#16a34a';
                        cellDiv.innerHTML = `<span class="font-medium" style="${color}">${gradeVal}</span>`;
                        cellDiv.style.background = '#ecfdf5';
                        delete pendingOpenedGrades[key];
                        cellDiv.classList.remove('grade-cell-opened');
                        cellDiv.onclick = null;
                    }
                });

                updatePendingPanel();
                btn.disabled = false;
                btn.style.opacity = '1';

                if (errorCount === 0) {
                    btn.textContent = 'Saqlash';
                    // Muvaffaqiyat — panelni yashirish
                    const notifDiv = document.createElement('div');
                    notifDiv.style.cssText = 'position:fixed; bottom:80px; left:50%; transform:translateX(-50%); z-index:99999; background:#10b981; color:#fff; padding:12px 24px; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,0.25); font-size:14px; font-weight:600;';
                    notifDiv.textContent = `${successCount} ta baho muvaffaqiyatli saqlandi!`;
                    document.body.appendChild(notifDiv);
                    setTimeout(() => notifDiv.remove(), 4000);
                } else {
                    // Xatolik — panelda ko'rsatish
                    btn.textContent = 'Qayta saqlash';
                    const panel = document.getElementById('pending-save-panel');
                    if (panel) {
                        panel.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
                        const infoEl = panel.querySelector('div > span');
                        if (infoEl) infoEl.innerHTML = `<b style="font-size:13px;">XATOLIK: ${firstErrMsg}</b>`;
                    }
                    // Alert ham ko'rsatish — aniq ko'rinishi uchun
                    alert('Saqlashda xatolik:\n\n' + firstErrMsg);
                }
            }).catch(err => {
                console.error('Promise.all error:', err);
                btn.disabled = false;
                btn.textContent = 'Saqlash';
                btn.style.opacity = '1';
                alert('Saqlashda kutilmagan xatolik: ' + err.message);
            });
        }

        // Sahifadan chiqishda ogohlantirish
        window.addEventListener('beforeunload', function(e) {
            if (Object.keys(pendingOpenedGrades).length > 0) {
                e.preventDefault();
                e.returnValue = 'Saqlanmagan baholar bor. Sahifadan chiqmoqchimisiz?';
            }
        });
    </script>
</x-app-layout>
