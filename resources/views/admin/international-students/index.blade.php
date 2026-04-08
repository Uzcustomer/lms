<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Xalqaro talabalar</h2>
            <a href="{{ route('admin.international-students.statistics') }}" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;font-size:12px;font-weight:600;color:#4f46e5;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;text-decoration:none;transition:all 0.15s;" onmouseover="this.style.background='#e0e7ff'" onmouseout="this.style.background='#eef2ff'">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                Statistika
            </a>
        </div>
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
                <form id="filterForm" method="GET" action="{{ route('admin.international-students.index') }}">
                    <div class="filter-container">
                        {{-- 1-qator --}}
                        <div class="filter-row">
                            <div class="filter-item" style="flex:2; min-width:180px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.Sh</label>
                                <div class="filter-wrap">
                                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Ism bo'yicha qidirish" class="filter-input" style="padding-right:28px;" onkeydown="if(event.key==='Enter'){document.getElementById('filterForm').submit();}">
                                    @if(request('search'))<button type="button" class="filter-clear" onclick="clearFilter('search')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:100px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <div class="filter-wrap">
                                    <select name="level_code" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                        <option value="">Barchasi</option>
                                        @php
                                            $levelCodes = \App\Models\Student::where(function($q){
                                                $q->where('group_name','like','xd%')->orWhere('citizenship_name','like','%orijiy%');
                                            })->whereNotNull('level_code')->select('level_code','level_name')->distinct()->orderBy('level_code')->get();
                                        @endphp
                                        @foreach($levelCodes as $lc)
                                            <option value="{{ $lc->level_code }}" {{ request('level_code') == $lc->level_code ? 'selected' : '' }}>{{ $lc->level_name }}</option>
                                        @endforeach
                                    </select>
                                    @if(request('level_code'))<button type="button" class="filter-clear" onclick="clearFilter('level_code')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:120px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <div class="filter-wrap">
                                    <input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="Guruh nomi" class="filter-input" style="padding-right:28px;" onkeydown="if(event.key==='Enter'){document.getElementById('filterForm').submit();}">
                                    @if(request('group_name'))<button type="button" class="filter-clear" onclick="clearFilter('group_name')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Davlati</label>
                                <div class="filter-wrap">
                                    <select name="country" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                        <option value="">Barchasi</option>
                                        @foreach($countries as $c)
                                            <option value="{{ $c }}" {{ request('country') === $c ? 'selected' : '' }}>{{ $c }}</option>
                                        @endforeach
                                    </select>
                                    @if(request('country'))<button type="button" class="filter-clear" onclick="clearFilter('country')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="flex:1; min-width:160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#047857;"></span> Fakultet</label>
                                <div class="filter-wrap">
                                    <select name="department" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                        <option value="">Barchasi</option>
                                        @foreach($departments as $d)
                                            <option value="{{ $d->department_id }}" {{ request('department') == $d->department_id ? 'selected' : '' }}>{{ $d->department_name }}</option>
                                        @endforeach
                                    </select>
                                    @if(request('department'))<button type="button" class="filter-clear" onclick="clearFilter('department')">&times;</button>@endif
                                </div>
                            </div>
                        </div>
                        {{-- 2-qator --}}
                        <div class="filter-row">
                            <div class="filter-item" style="min-width:120px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Firma</label>
                                <div class="filter-wrap">
                                    <select name="firm" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                        <option value="">Barchasi</option>
                                        @foreach($firms as $key => $label)
                                            <option value="{{ $key }}" {{ request('firm') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                        <option value="other" {{ request('firm') === 'other' ? 'selected' : '' }}>Boshqa</option>
                                        <option value="none" {{ request('firm') === 'none' ? 'selected' : '' }}>Belgilanmagan</option>
                                    </select>
                                    @if(request('firm'))<button type="button" class="filter-clear" onclick="clearFilter('firm')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Holati</label>
                                <div class="filter-wrap">
                                    <select name="data_status" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                    <option value="">Barchasi</option>
                                    <option value="filled" {{ request('data_status') === 'filled' ? 'selected' : '' }}>Kiritilgan</option>
                                    <option value="not_filled" {{ request('data_status') === 'not_filled' ? 'selected' : '' }}>Kiritilmagan</option>
                                    <option value="approved" {{ request('data_status') === 'approved' ? 'selected' : '' }}>Tasdiqlangan</option>
                                    <option value="pending" {{ request('data_status') === 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                                    <option value="rejected" {{ request('data_status') === 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                                    </select>
                                    @if(request('data_status'))<button type="button" class="filter-clear" onclick="clearFilter('data_status')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Viza tugash</label>
                                <div class="filter-wrap">
                                    <select name="visa_expiry" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                        <option value="">Barchasi</option>
                                        <option value="15" {{ request('visa_expiry') == '15' ? 'selected' : '' }}>15 kun</option>
                                        <option value="20" {{ request('visa_expiry') == '20' ? 'selected' : '' }}>20 kun</option>
                                        <option value="30" {{ request('visa_expiry') == '30' ? 'selected' : '' }}>30 kun</option>
                                    </select>
                                    @if(request('visa_expiry') !== null && request('visa_expiry') !== '')<button type="button" class="filter-clear" onclick="clearFilter('visa_expiry')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Reg. tugash</label>
                                <div class="filter-wrap">
                                    <select name="registration_expiry" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                        <option value="">Barchasi</option>
                                        <option value="3" {{ request('registration_expiry') == '3' ? 'selected' : '' }}>3 kun</option>
                                        <option value="5" {{ request('registration_expiry') == '5' ? 'selected' : '' }}>5 kun</option>
                                        <option value="7" {{ request('registration_expiry') == '7' ? 'selected' : '' }}>7 kun</option>
                                    </select>
                                    @if(request('registration_expiry') !== null && request('registration_expiry') !== '')<button type="button" class="filter-clear" onclick="clearFilter('registration_expiry')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#dc2626;"></span> HEMIS</label>
                                <div class="filter-wrap">
                                    <select name="hemis_status" class="filter-input" onchange="document.getElementById('filterForm').submit();" style="padding:0 8px;padding-right:28px;">
                                        <option value="">Barchasi</option>
                                        <option value="active" {{ request('hemis_status') === 'active' ? 'selected' : '' }}>Faol</option>
                                        <option value="inactive" {{ request('hemis_status') === 'inactive' ? 'selected' : '' }}>HEMIS'da yo'q</option>
                                    </select>
                                    @if(request('hemis_status'))<button type="button" class="filter-clear" onclick="clearFilter('hemis_status')">&times;</button>@endif
                                </div>
                            </div>
                            <div class="filter-item" style="min-width:120px;">
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Statistika --}}
                @php $baseUrl = route('admin.international-students.index'); @endphp
                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <a href="{{ $baseUrl }}" class="int-badge int-badge-primary" style="text-decoration:none;">Jami: {{ $stats['totalIntStudents'] }}</a>
                        <a href="{{ $baseUrl }}?data_status=filled" class="int-badge int-badge-green" style="text-decoration:none;">{{ $stats['filledCount'] }} kiritgan</a>
                        <a href="{{ $baseUrl }}?data_status=not_filled" class="int-badge int-badge-red-light" style="text-decoration:none;">{{ $stats['notFilledCount'] }} kiritmagan</a>
                        <a href="{{ $baseUrl }}?data_status=approved" class="int-badge int-badge-green-light" style="text-decoration:none;">{{ $stats['approvedCount'] }} tasdiqlangan</a>
                        <a href="{{ $baseUrl }}?data_status=pending" class="int-badge int-badge-yellow" style="text-decoration:none;">{{ $stats['pendingCount'] }} kutilmoqda</a>
                        @if($stats['expiredVisaCount'] > 0)
                            <a href="{{ $baseUrl }}?visa_expiry=0" class="int-badge int-badge-danger" style="text-decoration:none;">{{ $stats['expiredVisaCount'] }} viza muddati o'tgan!</a>
                        @endif
                        @if($stats['expiredRegCount'] > 0)
                            <a href="{{ $baseUrl }}?registration_expiry=0" class="int-badge int-badge-danger-orange" style="text-decoration:none;">{{ $stats['expiredRegCount'] }} registratsiya muddati o'tgan!</a>
                        @endif
                        @if($stats['visaUrgentCount'] > 0)
                            <a href="{{ $baseUrl }}?visa_expiry=30" class="int-badge int-badge-warning" style="text-decoration:none;">{{ $stats['visaUrgentCount'] }} viza yaqin (30k)</a>
                        @endif
                        @if($stats['regUrgentCount'] > 0)
                            <a href="{{ $baseUrl }}?registration_expiry=7" class="int-badge int-badge-warning" style="text-decoration:none;">{{ $stats['regUrgentCount'] }} registratsiya yaqin (7k)</a>
                        @endif
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        @if($isSubscribed)
                            <form method="POST" action="{{ route('admin.international-students.unsubscribe') }}" style="margin:0;">
                                @csrf
                                <button type="submit" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:12px;font-weight:600;color:#dc2626;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;cursor:pointer;white-space:nowrap;transition:all 0.15s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 005.714 0m-7.03-2.198a24.01 24.01 0 01-.758-.378 2.25 2.25 0 01-1.069-1.982V8.25a.75.75 0 01.75-.75h.006c.166 0 .33.01.493.028l.006.001a24.096 24.096 0 0115.318 0l.006-.001c.163-.018.327-.028.493-.028h.006a.75.75 0 01.75.75v4.272a2.25 2.25 0 01-1.069 1.982c-.252.155-.508.303-.758.378"/></svg>
                                    Obunadan chiqish
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.international-students.subscribe') }}" style="margin:0;">
                                @csrf
                                <button type="submit" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:12px;font-weight:600;color:#4f46e5;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;cursor:pointer;white-space:nowrap;transition:all 0.15s;" onmouseover="this.style.background='#e0e7ff'" onmouseout="this.style.background='#eef2ff'">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                                    Bildirishnomaga obuna
                                </button>
                            </form>
                        @endif
                    <form method="POST" action="{{ route('admin.international-students.notify-danger') }}" style="margin:0;">
                        @csrf
                        <button type="submit" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:12px;font-weight:600;color:#fff;background:#dc2626;border:1px solid #b91c1c;border-radius:8px;cursor:pointer;white-space:nowrap;" onclick="return confirm('Muddati qizil holatdagi barcha talabalarga bildirishnoma yuborilsinmi?')">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            Ogohlantirish yuborish
                        </button>
                    </form>
                    <a href="{{ route('admin.international-students.export', request()->all()) }}" class="int-btn-export">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Excel yuklab olish
                    </a>
                    </div>
                </div>

                {{-- Bulk action bar --}}
                <div id="bulkBar" style="display:none;padding:10px 20px;background:#eff6ff;border-bottom:1px solid #bfdbfe;" class="flex items-center justify-between">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:13px;font-weight:600;color:#1e40af;"><span id="selectedCount">0</span> ta talaba tanlandi</span>
                        <button type="button" onclick="clearSelection()" style="font-size:11px;padding:4px 10px;border:1px solid #93c5fd;background:#fff;border-radius:6px;color:#1e40af;cursor:pointer;">Bekor qilish</button>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button type="button" onclick="openFirmModal()" style="font-size:11px;padding:5px 14px;background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;">Firma biriktirish</button>
                        <button type="button" onclick="openRegModal()" style="font-size:11px;padding:5px 14px;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;">Reg. talabnoma</button>
                        <button type="button" onclick="openVizaModal()" style="font-size:11px;padding:5px 14px;background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;">Viza talabnoma</button>
                    </div>
                </div>

                {{-- Registratsiya talabnoma modal --}}
                <div id="regModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:12px;padding:24px;max-width:400px;width:90%;margin:auto;">
                        <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:16px;">Registratsiya talabnoma</h3>
                        <form id="regTalabnomaForm" method="POST" action="{{ route('admin.international-students.registration-talabnoma') }}">
                            @csrf
                            <div id="regInputs"></div>
                            <div style="margin-bottom:12px;">
                                <label style="font-size:12px;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Registratsiya muddati (oylarda)</label>
                                <select name="reg_months" required style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                                    <option value="3">3 oy</option>
                                    <option value="6">6 oy</option>
                                    <option value="12">12 oy</option>
                                </select>
                            </div>
                            <div style="display:flex;gap:8px;justify-content:flex-end;">
                                <button type="button" onclick="closeRegModal()" style="padding:8px 16px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;">Bekor</button>
                                <button type="submit" style="padding:8px 16px;font-size:12px;background:#2b5ea7;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Yaratish</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Viza talabnoma modal --}}
                <div id="vizaModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:12px;padding:24px;max-width:400px;width:90%;margin:auto;">
                        <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:16px;">Viza talabnoma</h3>
                        <form id="vizaTalabnomaForm" method="POST" action="{{ route('admin.international-students.visa-talabnoma') }}">
                            @csrf
                            <div id="vizaInputs"></div>
                            <div style="margin-bottom:12px;">
                                <label style="font-size:12px;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Viza muddati (oylarda)</label>
                                <select name="visa_months" required style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                                    <option value="3">3 oy</option>
                                    <option value="6">6 oy</option>
                                    <option value="12">12 oy</option>
                                </select>
                            </div>
                            <div style="margin-bottom:12px;">
                                <label style="font-size:12px;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Necha martalik</label>
                                <select name="visa_entries" required style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                                    <option value="1">1 martalik (ONE)</option>
                                    <option value="2" selected>2 martalik (TWO)</option>
                                    <option value="3">3 martalik (THREE)</option>
                                    <option value="99">Ko'p martalik (MULTIPLE)</option>
                                </select>
                            </div>
                            <div style="display:flex;gap:8px;justify-content:flex-end;">
                                <button type="button" onclick="closeVizaModal()" style="padding:8px 16px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;">Bekor</button>
                                <button type="submit" style="padding:8px 16px;font-size:12px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Yaratish</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Firma biriktirish modal --}}
                <div id="firmModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:12px;padding:24px;max-width:400px;width:90%;margin:auto;">
                        <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin-bottom:16px;">Firma biriktirish</h3>
                        <form id="firmAssignForm" method="POST" action="{{ route('admin.international-students.bulk-assign-firm') }}">
                            @csrf
                            <div id="firmInputs"></div>
                            <div style="margin-bottom:12px;">
                                <label style="font-size:12px;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Firma tanlang</label>
                                <select name="firm" required style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                                    @foreach($firms as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                    <option value="other">Boshqa</option>
                                </select>
                            </div>
                            <div style="display:flex;gap:8px;justify-content:flex-end;">
                                <button type="button" onclick="closeFirmModal()" style="padding:8px 16px;font-size:12px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;">Bekor</button>
                                <button type="submit" style="padding:8px 16px;font-size:12px;background:#d97706;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Biriktirish</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Jadval --}}
                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th style="width:36px;text-align:center;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" style="accent-color:#2b5ea7;cursor:pointer;">
                                </th>
                                <th>Talaba ID</th>
                                <th>F.I.Sh</th>
                                <th>Davlati</th>
                                <th>Kurs</th>
                                <th>Fakultet</th>
                                <th>Yo'nalish</th>
                                <th>Guruh</th>
                                <th>Ma'lumot</th>
                                <th>Reg. tugash</th>
                                <th>Viza tugash</th>
                                <th>Firma</th>
                                <th>Holat</th>
                                <th style="text-align:center;">Pasport</th>
                                <th style="text-align:center;">Jarayon</th>
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
                                <tr class="{{ $isUrgent ? 'int-row-urgent' : '' }}" onclick="window.location='{{ route('admin.international-students.show', $student) }}'" style="cursor:pointer;">
                                    <td style="text-align:center;" onclick="event.stopPropagation();">
                                        <input type="checkbox" class="student-cb" value="{{ $student->id }}" onchange="updateBulkBar()" style="accent-color:#2b5ea7;cursor:pointer;">
                                    </td>
                                    <td style="color:#64748b;font-size:12px;">{{ $student->student_id_number }}</td>
                                    <td>
                                        <a href="{{ route('admin.international-students.show', $student) }}" class="student-name-link">{{ $student->full_name }}</a>
                                        @if($student->student_status_code == '60')
                                            <span style="display:inline-block;margin-left:4px;padding:1px 6px;font-size:9px;font-weight:700;border-radius:4px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;">HEMIS'da yo'q</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-cell" style="font-weight:600;">{{ $student->country_name ?? '—' }}</span>
                                        @if($student->citizenship_name)
                                            <span style="font-size:10px;color:#94a3b8;">{{ $student->citizenship_name }}</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-violet">{{ $student->level_name }}</span></td>
                                    <td><span class="text-cell text-emerald">{{ $student->department_name }}</span></td>
                                    <td><span class="text-cell text-cyan" title="{{ $student->specialty_name }}">{{ Str::limit($student->specialty_name, 25) }}</span></td>
                                    <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                    <td>
                                        @php $hasRealData = $visa && ($visa->passport_number || $visa->visa_number || $visa->registration_end_date); @endphp
                                        @if($hasRealData || $falseShowEnabled)
                                            <span class="int-status-pill int-status-green">Kiritilgan</span>
                                        @else
                                            <span class="int-status-pill int-status-red">Kiritilmagan</span>
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
                                        @if($hasRealData)
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
                                    <td style="text-align:center;" onclick="event.stopPropagation();">
                                        @if($visa)
                                            @php
                                                $rp = $visa->registration_process_status ?? 'none';
                                                $vp = $visa->visa_process_status ?? 'none';
                                                $canAccept = in_array($rp, ['none','done']) && in_array($vp, ['none','done']);
                                            @endphp
                                            @if($vp === 'passport_accepted')
                                                <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;background:#dbeafe;color:#1e40af;">V: Pasport olindi</span>
                                            @elseif($vp === 'registering')
                                                <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">
                                                    @csrf <input type="hidden" name="process_type" value="visa">
                                                    <button type="submit" class="btn-action" style="background:#fef3c7;color:#92400e;font-size:10px;border:1px solid #fde68a;" onclick="return confirm('Viza yangilandi?')">Viza yangilandi</button>
                                                </form>
                                            @elseif($rp === 'passport_accepted')
                                                <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;background:#dbeafe;color:#1e40af;">R: Pasport olindi</span>
                                            @elseif($rp === 'registering')
                                                <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">
                                                    @csrf <input type="hidden" name="process_type" value="registration">
                                                    <button type="submit" class="btn-action" style="background:#fef3c7;color:#92400e;font-size:10px;border:1px solid #fde68a;" onclick="return confirm('Reg. yangilandi?')">Reg. yangilandi</button>
                                                </form>
                                            @elseif(!$visa->passport_handed_over && $canAccept)
                                                <div style="display:flex;flex-direction:column;gap:3px;">
                                                    <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">
                                                        @csrf <input type="hidden" name="process_type" value="registration">
                                                        <button type="submit" class="btn-action" style="background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;font-size:9px;width:100%;" onclick="return confirm('Reg. uchun pasport?')">Reg. pasport</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">
                                                        @csrf <input type="hidden" name="process_type" value="visa">
                                                        <button type="submit" class="btn-action" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;font-size:9px;width:100%;" onclick="return confirm('Viza uchun pasport?')">Viza pasport</button>
                                                    </form>
                                                </div>
                                            @elseif($rp === 'done' || $vp === 'done')
                                                <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px;white-space:nowrap;background:#dcfce7;color:#166534;">Tugallandi</span>
                                            @else
                                                <span style="color:#cbd5e1;">—</span>
                                            @endif
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" style="text-align:center;padding:40px 20px;color:#94a3b8;font-size:14px;">Talabalar topilmadi</td>
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
    .filter-wrap { position: relative; }
    .filter-clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; border-radius: 50%; border: none; background: #94a3b8; color: #fff; font-size: 13px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; transition: background 0.15s; }
    .filter-clear:hover { background: #ef4444; }
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
    .text-emerald { color: #047857; }
    .text-cyan { color: #0e7490; max-width: 180px; white-space: normal; word-break: break-word; }

    .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
    .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
    .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
    .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }

    .btn-action { display: inline-block; padding: 4px 12px; font-size: 11px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
    .btn-action:hover { transform: translateY(-1px); }
    .btn-action-blue { background: linear-gradient(135deg, #2b5ea7, #3b82f6); color: #fff; }
    .btn-action-blue:hover { box-shadow: 0 2px 8px rgba(59,130,246,0.4); }

    /* Statistika badgelar */
    .int-badge { display: inline-block; padding: 5px 12px; font-size: 12px; font-weight: 600; border-radius: 8px; white-space: nowrap; cursor: pointer; transition: all 0.15s; }
    .int-badge:hover { opacity: 0.8; transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
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

<script>
// Auto-submit: select o'zgarganda form yuboriladi
document.querySelectorAll('.auto-submit').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Bitta filtrni tozalash
function clearFilter(name) {
    var el = document.querySelector('[name="' + name + '"]');
    if (el) {
        el.value = '';
        document.getElementById('filterForm').submit();
    }
}

function toggleSelectAll() {
    var checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.student-cb').forEach(function(cb) { cb.checked = checked; });
    updateBulkBar();
}

function updateBulkBar() {
    var checked = document.querySelectorAll('.student-cb:checked');
    var bar = document.getElementById('bulkBar');
    var count = document.getElementById('selectedCount');
    count.textContent = checked.length;
    bar.style.display = checked.length > 0 ? 'flex' : 'none';

    // Update hidden inputs for forms
    var regInputs = document.getElementById('regInputs');
    var vizaInputs = document.getElementById('vizaInputs');
    regInputs.innerHTML = '';
    vizaInputs.innerHTML = '';
    checked.forEach(function(cb) {
        regInputs.innerHTML += '<input type="hidden" name="student_ids[]" value="' + cb.value + '">';
        vizaInputs.innerHTML += '<input type="hidden" name="student_ids[]" value="' + cb.value + '">';
    });

    // selectAll indeterminate
    var all = document.querySelectorAll('.student-cb');
    var selectAll = document.getElementById('selectAll');
    if (checked.length === 0) { selectAll.checked = false; selectAll.indeterminate = false; }
    else if (checked.length === all.length) { selectAll.checked = true; selectAll.indeterminate = false; }
    else { selectAll.indeterminate = true; }
}

function clearSelection() {
    document.querySelectorAll('.student-cb').forEach(function(cb) { cb.checked = false; });
    document.getElementById('selectAll').checked = false;
    document.getElementById('selectAll').indeterminate = false;
    updateBulkBar();
}

function openRegModal() {
    syncInputs('regInputs');
    document.getElementById('regModal').style.display = 'flex';
}
function closeRegModal() { document.getElementById('regModal').style.display = 'none'; }
function openVizaModal() {
    syncInputs('vizaInputs');
    document.getElementById('vizaModal').style.display = 'flex';
}
function closeVizaModal() { document.getElementById('vizaModal').style.display = 'none'; }
function openFirmModal() { syncInputs('firmInputs'); document.getElementById('firmModal').style.display = 'flex'; }
function closeFirmModal() { document.getElementById('firmModal').style.display = 'none'; }
function syncInputs(containerId) {
    var c = document.getElementById(containerId);
    c.innerHTML = '';
    document.querySelectorAll('.student-cb:checked').forEach(function(cb) {
        c.innerHTML += '<input type="hidden" name="student_ids[]" value="' + cb.value + '">';
    });
}
</script>
</x-app-layout>
