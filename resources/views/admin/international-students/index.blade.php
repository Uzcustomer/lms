<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Xalqaro talabalar</h2>
    </x-slot>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
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
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ma'lumot holati</label>
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
                                <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Propiska tugash</label>
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

                {{-- Statistika paneli --}}
                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="badge" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ $stats['totalIntStudents'] }} ta talaba</span>
                        <span class="badge" style="background:#dcfce7;color:#166534;padding:6px 12px;font-size:12px;border-radius:8px;border:1px solid #bbf7d0;">{{ $stats['filledCount'] }} kiritgan</span>
                        <span class="badge" style="background:#fef2f2;color:#991b1b;padding:6px 12px;font-size:12px;border-radius:8px;border:1px solid #fecaca;">{{ $stats['notFilledCount'] }} kiritmagan</span>
                        <span class="badge" style="background:#f0fdf4;color:#166534;padding:6px 12px;font-size:12px;border-radius:8px;border:1px solid #bbf7d0;">{{ $stats['approvedCount'] }} tasdiqlangan</span>
                        <span class="badge" style="background:#fefce8;color:#854d0e;padding:6px 12px;font-size:12px;border-radius:8px;border:1px solid #fef08a;">{{ $stats['pendingCount'] }} kutilmoqda</span>
                        @if($stats['expiredVisaCount'] > 0)
                            <span class="badge" style="background:#dc2626;color:#fff;padding:6px 12px;font-size:12px;border-radius:8px;font-weight:700;">{{ $stats['expiredVisaCount'] }} viza muddati o'tgan!</span>
                        @endif
                        @if($stats['expiredRegCount'] > 0)
                            <span class="badge" style="background:#ea580c;color:#fff;padding:6px 12px;font-size:12px;border-radius:8px;font-weight:700;">{{ $stats['expiredRegCount'] }} propiska muddati o'tgan!</span>
                        @endif
                        @if($stats['visaUrgentCount'] > 0)
                            <span class="badge" style="background:#fff7ed;color:#c2410c;padding:6px 12px;font-size:12px;border-radius:8px;border:1px solid #fed7aa;">{{ $stats['visaUrgentCount'] }} viza yaqin (30k)</span>
                        @endif
                        @if($stats['regUrgentCount'] > 0)
                            <span class="badge" style="background:#fff7ed;color:#c2410c;padding:6px 12px;font-size:12px;border-radius:8px;border:1px solid #fed7aa;">{{ $stats['regUrgentCount'] }} propiska yaqin (7k)</span>
                        @endif
                    </div>
                    <a href="{{ route('admin.international-students.export', request()->all()) }}"
                       style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:13px;font-weight:600;color:#fff;background:linear-gradient(135deg,#16a34a,#22c55e);border-radius:8px;text-decoration:none;transition:opacity 0.2s;white-space:nowrap;"
                       onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
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
                                <th>F.I.Sh</th>
                                <th>Guruh</th>
                                <th>Kurs</th>
                                <th>Ma'lumot</th>
                                <th>Propiska tugash</th>
                                <th>Viza tugash</th>
                                <th>Firma</th>
                                <th>Holat</th>
                                <th>Pasport</th>
                                <th style="text-align:center;">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $student)
                                @php
                                    $visa = $student->visaInfo;
                                    $regDays = $visa?->registrationDaysLeft();
                                    $visaDays = $visa?->visaDaysLeft();
                                @endphp
                                <tr style="{{ ($regDays !== null && $regDays <= 3) || ($visaDays !== null && $visaDays <= 15) ? 'background:#fef2f2 !important;' : '' }}">
                                    <td>
                                        <a href="{{ route('admin.international-students.show', $student) }}" class="student-name-link">{{ $student->full_name }}</a>
                                    </td>
                                    <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                    <td><span class="badge badge-violet">{{ $student->level_name }}</span></td>
                                    <td>
                                        @if($visa)
                                            <span style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;font-size:11px;font-weight:600;border-radius:20px;background:#dcfce7;color:#166534;">
                                                <svg style="width:12px;height:12px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Kiritilgan
                                            </span>
                                        @else
                                            <span style="display:inline-flex;align-items:center;gap:3px;padding:3px 10px;font-size:11px;font-weight:600;border-radius:20px;background:#fef2f2;color:#991b1b;">
                                                <svg style="width:12px;height:12px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                Kiritilmagan
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($visa?->registration_end_date)
                                            <div style="font-size:12px;font-weight:{{ $regDays <= 5 ? '700' : '500' }};color:{{ $regDays <= 3 ? '#dc2626' : ($regDays <= 5 ? '#d97706' : ($regDays <= 7 ? '#16a34a' : '#475569')) }};">
                                                {{ $visa->registration_end_date->format('d.m.Y') }}
                                            </div>
                                            @if($regDays !== null && $regDays <= 7)
                                                <span style="display:inline-block;margin-top:2px;padding:1px 8px;font-size:10px;font-weight:700;border-radius:4px;color:#fff;background:{{ $regDays <= 0 ? '#dc2626' : ($regDays <= 3 ? '#ef4444' : ($regDays <= 5 ? '#f59e0b' : '#22c55e')) }};">
                                                    {{ $regDays <= 0 ? 'TUGAGAN' : $regDays . ' kun' }}
                                                </span>
                                            @endif
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($visa?->visa_end_date)
                                            <div style="font-size:12px;font-weight:{{ $visaDays <= 20 ? '700' : '500' }};color:{{ $visaDays <= 15 ? '#dc2626' : ($visaDays <= 20 ? '#d97706' : ($visaDays <= 30 ? '#16a34a' : '#475569')) }};">
                                                {{ $visa->visa_end_date->format('d.m.Y') }}
                                            </div>
                                            @if($visaDays !== null && $visaDays <= 30)
                                                <span style="display:inline-block;margin-top:2px;padding:1px 8px;font-size:10px;font-weight:700;border-radius:4px;color:#fff;background:{{ $visaDays <= 0 ? '#dc2626' : ($visaDays <= 15 ? '#ef4444' : ($visaDays <= 20 ? '#f59e0b' : '#22c55e')) }};">
                                                    {{ $visaDays <= 0 ? 'TUGAGAN' : $visaDays . ' kun' }}
                                                </span>
                                            @endif
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                    <td style="font-size:12px;color:#475569;">{{ $visa?->firm_display ?? '—' }}</td>
                                    <td>
                                        @if($visa)
                                            @if($visa->status === 'approved')
                                                <span style="padding:3px 10px;font-size:11px;font-weight:600;border-radius:20px;background:#dcfce7;color:#166534;">Tasdiqlangan</span>
                                            @elseif($visa->status === 'rejected')
                                                <span style="padding:3px 10px;font-size:11px;font-weight:600;border-radius:20px;background:#fef2f2;color:#991b1b;">Rad etilgan</span>
                                            @else
                                                <span style="padding:3px 10px;font-size:11px;font-weight:600;border-radius:20px;background:#fefce8;color:#854d0e;">Kutilmoqda</span>
                                            @endif
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        @if($visa?->passport_handed_over)
                                            <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#dcfce7;">
                                                <svg style="width:14px;height:14px;color:#16a34a;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            </span>
                                        @elseif($visa)
                                            <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#fef2f2;">
                                                <svg style="width:14px;height:14px;color:#dc2626;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            </span>
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="{{ route('admin.international-students.show', $student) }}" class="btn-action btn-action-blue">Ko'rish</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" style="text-align:center;padding:40px 20px;color:#94a3b8;">Talabalar topilmadi</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:between;">
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
                            {{ $students->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
