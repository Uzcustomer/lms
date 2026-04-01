<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Xalqaro talabalar</h2>
    </x-slot>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                {{-- Filtrlar --}}
                <form method="GET" action="{{ route('admin.international-students.index') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="flex:1; min-width:200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.Sh</label>
                                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ism bo'yicha qidirish" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width:120px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select name="level_code" class="filter-input" style="padding:0 8px;">
                                    <option value="">Barchasi</option>
                                    @for($i = 1; $i <= 6; $i++)
                                        <option value="{{ $i }}" {{ request('level_code') == $i ? 'selected' : '' }}>{{ $i }}-kurs</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:150px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="Guruh nomi" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width:140px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Firma</label>
                                <select name="firm" class="filter-input" style="padding:0 8px;">
                                    <option value="">Barchasi</option>
                                    @foreach($firms as $key => $label)
                                        <option value="{{ $key }}" {{ request('firm') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                    <option value="other" {{ request('firm') === 'other' ? 'selected' : '' }}>Boshqa</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:150px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Holati</label>
                                <select name="data_status" class="filter-input" style="padding:0 8px;">
                                    <option value="">Barchasi</option>
                                    <option value="filled" {{ request('data_status') === 'filled' ? 'selected' : '' }}>Kiritilgan</option>
                                    <option value="not_filled" {{ request('data_status') === 'not_filled' ? 'selected' : '' }}>Kiritilmagan</option>
                                    <option value="approved" {{ request('data_status') === 'approved' ? 'selected' : '' }}>Tasdiqlangan</option>
                                    <option value="pending" {{ request('data_status') === 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                                    <option value="rejected" {{ request('data_status') === 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-item" style="min-width:150px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Viza tugash</label>
                                <select name="visa_expiry" class="filter-input" style="padding:0 8px;">
                                    <option value="">Barchasi</option>
                                    <option value="15" {{ request('visa_expiry') == '15' ? 'selected' : '' }}>15 kun ichida</option>
                                    <option value="20" {{ request('visa_expiry') == '20' ? 'selected' : '' }}>20 kun ichida</option>
                                    <option value="30" {{ request('visa_expiry') == '30' ? 'selected' : '' }}>30 kun ichida</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Registratsiya tugash</label>
                                <select name="registration_expiry" class="filter-input" style="padding:0 8px;">
                                    <option value="">Barchasi</option>
                                    <option value="3" {{ request('registration_expiry') == '3' ? 'selected' : '' }}>3 kun ichida</option>
                                    <option value="5" {{ request('registration_expiry') == '5' ? 'selected' : '' }}>5 kun ichida</option>
                                    <option value="7" {{ request('registration_expiry') == '7' ? 'selected' : '' }}>7 kun ichida</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:120px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Statistika --}}
                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="int-badge int-badge-primary">Jami: {{ $stats['totalIntStudents'] }} ta talaba</span>
                        <span class="int-badge int-badge-green">{{ $stats['filledCount'] }} kiritgan</span>
                        <span class="int-badge int-badge-red-light">{{ $stats['notFilledCount'] }} kiritmagan</span>
                        <span class="int-badge int-badge-green-light">{{ $stats['approvedCount'] }} tasdiqlangan</span>
                        <span class="int-badge int-badge-yellow">{{ $stats['pendingCount'] }} kutilmoqda</span>
                        @if($stats['expiredVisaCount'] > 0)
                            <span class="int-badge int-badge-danger">{{ $stats['expiredVisaCount'] }} viza muddati o'tgan!</span>
                        @endif
                        @if($stats['expiredRegCount'] > 0)
                            <span class="int-badge int-badge-danger-orange">{{ $stats['expiredRegCount'] }} registratsiya muddati o'tgan!</span>
                        @endif
                        @if($stats['visaUrgentCount'] > 0)
                            <span class="int-badge int-badge-warning">{{ $stats['visaUrgentCount'] }} viza yaqin (30k)</span>
                        @endif
                        @if($stats['regUrgentCount'] > 0)
                            <span class="int-badge int-badge-warning">{{ $stats['regUrgentCount'] }} registratsiya yaqin (7k)</span>
                        @endif
                    </div>
                    <a href="{{ route('admin.international-students.export', request()->all()) }}" class="int-btn-export">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Excel yuklab olish
                    </a>
                </div>

                {{-- Jadval --}}
                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;">#</th>
                                <th>F.I.Sh</th>
                                <th>Guruh</th>
                                <th>Kurs</th>
                                <th>Ma'lumot</th>
                                <th>Registratsiya tugash</th>
                                <th>Viza tugash</th>
                                <th>Firma</th>
                                <th>Holat</th>
                                <th style="text-align:center;">Pasport</th>
                                <th>Jarayon</th>
                                <th style="text-align:center;">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $i => $student)
                                @php
                                    $visa = $student->visaInfo;
                                    $regDays = $visa?->registrationDaysLeft();
                                    $visaDays = $visa?->visaDaysLeft();
                                    $isUrgent = ($regDays !== null && $regDays <= 3) || ($visaDays !== null && $visaDays <= 15);
                                @endphp
                                <tr class="{{ $isUrgent ? 'int-row-urgent' : '' }}">
                                    <td style="text-align:center;color:#94a3b8;font-size:12px;">{{ $students->firstItem() + $i }}</td>
                                    <td>
                                        <a href="{{ route('admin.international-students.show', $student) }}" class="student-name-link">{{ $student->full_name }}</a>
                                    </td>
                                    <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                    <td><span class="badge badge-violet">{{ $student->level_name }}</span></td>
                                    <td>
                                        @if($visa)
                                            <span class="int-status-pill int-status-green">
                                                <svg style="width:12px;height:12px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Kiritilgan
                                            </span>
                                        @else
                                            <span class="int-status-pill int-status-red">
                                                <svg style="width:12px;height:12px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                Kiritilmagan
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($visa?->registration_end_date)
                                            <span class="int-date {{ $regDays <= 3 ? 'int-date-danger' : ($regDays <= 5 ? 'int-date-warn' : ($regDays <= 7 ? 'int-date-ok' : '')) }}">
                                                {{ $visa->registration_end_date->format('d.m.Y') }}
                                            </span>
                                            @if($regDays !== null && $regDays <= 7)
                                                <span class="int-days-badge {{ $regDays <= 0 ? 'int-days-expired' : ($regDays <= 3 ? 'int-days-danger' : ($regDays <= 5 ? 'int-days-warn' : 'int-days-ok')) }}">
                                                    {{ $regDays <= 0 ? 'TUGAGAN' : $regDays . ' kun' }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="int-empty">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($visa?->visa_end_date)
                                            <span class="int-date {{ $visaDays <= 15 ? 'int-date-danger' : ($visaDays <= 20 ? 'int-date-warn' : ($visaDays <= 30 ? 'int-date-ok' : '')) }}">
                                                {{ $visa->visa_end_date->format('d.m.Y') }}
                                            </span>
                                            @if($visaDays !== null && $visaDays <= 30)
                                                <span class="int-days-badge {{ $visaDays <= 0 ? 'int-days-expired' : ($visaDays <= 15 ? 'int-days-danger' : ($visaDays <= 20 ? 'int-days-warn' : 'int-days-ok')) }}">
                                                    {{ $visaDays <= 0 ? 'TUGAGAN' : $visaDays . ' kun' }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="int-empty">—</span>
                                        @endif
                                    </td>
                                    <td><span class="text-cell">{{ $visa?->firm_display ?? '—' }}</span></td>
                                    <td>
                                        @if($visa)
                                            @if($visa->status === 'approved')
                                                <span class="int-status-pill int-status-green">Tasdiqlangan</span>
                                            @elseif($visa->status === 'rejected')
                                                <span class="int-status-pill int-status-red">Rad etilgan</span>
                                            @else
                                                <span class="int-status-pill int-status-yellow">Kutilmoqda</span>
                                            @endif
                                        @else
                                            <span class="int-empty">—</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        @if($visa?->passport_handed_over)
                                            <span class="int-circle int-circle-green">
                                                <svg style="width:14px;height:14px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            </span>
                                        @elseif($visa)
                                            <span class="int-circle int-circle-red">
                                                <svg style="width:14px;height:14px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            </span>
                                        @else
                                            <span class="int-empty">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($visa)
                                            @php
                                                $rp = $visa->registration_process_status ?? 'none';
                                                $vp = $visa->visa_process_status ?? 'none';
                                            @endphp
                                            <div style="display:flex;flex-direction:column;gap:2px;">
                                                @if($rp !== 'none')
                                                    <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;white-space:nowrap;{{ match($rp) { 'passport_accepted' => 'background:#dbeafe;color:#1e40af;', 'registering' => 'background:#fef3c7;color:#92400e;', 'done' => 'background:#dcfce7;color:#166534;', default => '' } }}">R: {{ match($rp) { 'passport_accepted' => 'Pasport olindi', 'registering' => 'Qilinmoqda', 'done' => 'Tugallandi', default => '' } }}</span>
                                                @endif
                                                @if($vp !== 'none')
                                                    <span style="font-size:10px;font-weight:600;padding:1px 6px;border-radius:4px;white-space:nowrap;{{ match($vp) { 'passport_accepted' => 'background:#dbeafe;color:#1e40af;', 'registering' => 'background:#fef3c7;color:#92400e;', 'done' => 'background:#dcfce7;color:#166534;', default => '' } }}">V: {{ match($vp) { 'passport_accepted' => 'Pasport olindi', 'registering' => 'Yangilanmoqda', 'done' => 'Tugallandi', default => '' } }}</span>
                                                @endif
                                                @if($rp === 'none' && $vp === 'none')
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </div>
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex;flex-direction:column;align-items:center;gap:3px;">
                                            <a href="{{ route('admin.international-students.show', $student) }}" class="btn-action btn-action-blue" style="text-decoration:none;">Ko'rish</a>
                                            @if($visa && !$visa->passport_handed_over)
                                                @php
                                                    $rp = $visa->registration_process_status ?? 'none';
                                                    $vp = $visa->visa_process_status ?? 'none';
                                                    $canAccept = in_array($rp, ['none','done']) || in_array($vp, ['none','done']);
                                                @endphp
                                                @if($canAccept)
                                                    <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}" onclick="event.stopPropagation();">
                                                        @csrf <input type="hidden" name="process_type" value="visa">
                                                        <button type="submit" class="btn-action" style="background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;font-size:10px;" onclick="return confirm('Pasportni qabul qilasizmi?')">Pasport olish</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" style="text-align:center;padding:40px 20px;color:#94a3b8;font-size:14px;">Talabalar topilmadi</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($students->hasPages())
                <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                    <div class="flex-1 flex justify-between sm:hidden">
                        {{ $students->appends(request()->query())->links('pagination::simple-tailwind') }}
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-700">
                            {!! __('Showing') !!}
                            <span class="font-medium">{{ $students->firstItem() }}</span>
                            {!! __('to') !!}
                            <span class="font-medium">{{ $students->lastItem() }}</span>
                            {!! __('of') !!}
                            <span class="font-medium">{{ $students->total() }}</span>
                            {!! __('results') !!}
                        </p>
                        <div>{{ $students->appends(request()->query())->links() }}</div>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>

<style>
    /* Filter — Talabalar sahifasidek */
    .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
    .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
    .filter-row:last-child { margin-bottom: 0; }
    .filter-item { display: flex; flex-direction: column; }
    .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
    .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .filter-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; box-sizing: border-box; }
    .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
    .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.2); }
    .filter-input::placeholder { color: #94a3b8; }
    .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
    .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

    /* Jadval — Talabalar sahifasidek */
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
    .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
    .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
    .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }

    .btn-action { display: inline-block; padding: 4px 12px; font-size: 11px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
    .btn-action:hover { transform: translateY(-1px); }
    .btn-action-blue { background: linear-gradient(135deg, #2b5ea7, #3b82f6); color: #fff; }
    .btn-action-blue:hover { box-shadow: 0 2px 8px rgba(59,130,246,0.4); }

    /* Statistika badgelar */
    .int-badge { display: inline-block; padding: 5px 12px; font-size: 12px; font-weight: 600; border-radius: 8px; white-space: nowrap; }
    .int-badge-primary { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; }
    .int-badge-green { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .int-badge-red-light { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .int-badge-green-light { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .int-badge-yellow { background: #fefce8; color: #854d0e; border: 1px solid #fef08a; }
    .int-badge-danger { background: #dc2626; color: #fff; font-weight: 700; }
    .int-badge-danger-orange { background: #ea580c; color: #fff; font-weight: 700; }
    .int-badge-warning { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .int-btn-export { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 13px; font-weight: 600; color: #fff; background: linear-gradient(135deg, #16a34a, #22c55e); border-radius: 8px; text-decoration: none; transition: opacity 0.2s; white-space: nowrap; }
    .int-btn-export:hover { opacity: 0.85; }

    /* Status pillari */
    .int-status-pill { display: inline-flex; align-items: center; gap: 3px; padding: 3px 10px; font-size: 11px; font-weight: 600; border-radius: 20px; white-space: nowrap; }
    .int-status-green { background: #dcfce7; color: #166534; }
    .int-status-red { background: #fef2f2; color: #991b1b; }
    .int-status-yellow { background: #fefce8; color: #854d0e; }

    /* Sana ko'rsatkichlari */
    .int-date { display: block; font-size: 12px; font-weight: 500; color: #475569; }
    .int-date-danger { color: #dc2626; font-weight: 700; }
    .int-date-warn { color: #d97706; font-weight: 700; }
    .int-date-ok { color: #16a34a; font-weight: 600; }

    .int-days-badge { display: inline-block; margin-top: 2px; padding: 1px 8px; font-size: 10px; font-weight: 700; border-radius: 4px; color: #fff; }
    .int-days-expired { background: #dc2626; }
    .int-days-danger { background: #ef4444; }
    .int-days-warn { background: #f59e0b; }
    .int-days-ok { background: #22c55e; }

    /* Doira ikonkalar */
    .int-circle { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; }
    .int-circle-green { background: #dcfce7; color: #16a34a; }
    .int-circle-red { background: #fef2f2; color: #dc2626; }

    .int-empty { color: #cbd5e1; }
    .int-row-urgent { background: #fef2f2 !important; }
    .int-row-urgent:hover { background: #fee2e2 !important; box-shadow: inset 4px 0 0 #dc2626; }
</style>
</x-app-layout>
