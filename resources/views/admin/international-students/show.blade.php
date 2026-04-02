<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.international-students.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h2 class="font-semibold text-sm text-gray-800 leading-tight">{{ $student->full_name }}</h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">
            @if(session('success'))
                <div class="mb-3 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
            @endif

            @if(!$visaInfo)
                <div class="bg-white shadow-sm rounded-xl p-6 border border-gray-100">
                    <div class="flex items-center gap-4 mb-5">
                        <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;">{{ mb_substr($student->full_name, 0, 1) }}</div>
                        <div>
                            <div class="font-bold text-gray-800">{{ $student->full_name }}</div>
                            <div class="text-xs text-gray-500">{{ $student->group_name }} · {{ $student->level_name }} · {{ $student->country_name ?? '' }}</div>
                        </div>
                    </div>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto mb-3 text-yellow-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        <p class="text-sm font-medium text-yellow-700">Talaba hali viza ma'lumotlarini kiritmagan.</p>
                    </div>
                </div>
            @else
                @php
                    $rps = $visaInfo->registration_process_status ?? 'none';
                    $vps = $visaInfo->visa_process_status ?? 'none';
                    $regDays = $visaInfo->registrationDaysLeft();
                    $visaDays = $visaInfo->visaDaysLeft();
                @endphp

                {{-- Header --}}
                <div style="background:linear-gradient(135deg,#f0f4f8,#e8edf5);border:1px solid #dbe4ef;border-radius:12px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:15px;">{{ mb_substr($student->full_name, 0, 1) }}</div>
                        <div>
                            <div style="font-weight:700;color:#1e293b;font-size:15px;">{{ $student->full_name }}</div>
                            <div style="font-size:12px;color:#64748b;">{{ $student->group_name }} · {{ $student->level_name }} · <span style="color:#2b5ea7;font-weight:600;">{{ $student->country_name ?? '-' }}</span> · {{ $student->department_name }} @if($student->phone)· {{ $student->phone }}@endif</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        @if($visaInfo->status === 'approved')
                            <span style="padding:4px 12px;font-size:11px;font-weight:700;border-radius:20px;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">Tasdiqlangan</span>
                        @elseif($visaInfo->status === 'rejected')
                            <span style="padding:4px 12px;font-size:11px;font-weight:700;border-radius:20px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">Rad etilgan</span>
                        @else
                            <span style="padding:4px 12px;font-size:11px;font-weight:700;border-radius:20px;background:#fefce8;color:#854d0e;border:1px solid #fef08a;">Tekshirilmoqda</span>
                        @endif
                        @if($visaInfo->firm)
                            <span style="padding:4px 12px;font-size:11px;font-weight:700;border-radius:20px;background:linear-gradient(135deg,#1a3268,#2b5ea7);color:#fff;">{{ $visaInfo->firm_display }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    {{-- CHAP: Amallar --}}
                    <div class="lg:col-span-4 space-y-3">
                        {{-- Firma --}}
                        <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
                            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Firma biriktirish</div>
                            <form method="POST" action="{{ route('admin.international-students.assign-firm', $student) }}" style="display:flex;gap:6px;">
                                @csrf
                                <select name="firm" style="flex:1;font-size:12px;padding:6px 10px;border:1px solid #e2e8f0;border-radius:8px;outline:none;" onchange="this.form.querySelector('.fo').style.display=this.value==='other'?'block':'none'">
                                    <option value="">Tanlang</option>
                                    @foreach(\App\Models\StudentVisaInfo::FIRM_OPTIONS as $key => $label)
                                        <option value="{{ $key }}" {{ $visaInfo->firm === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                    <option value="other" {{ $visaInfo->firm === 'other' ? 'selected' : '' }}>Boshqa</option>
                                </select>
                                <button type="submit" class="btn-calc" style="height:auto;padding:6px 14px;font-size:11px;">Saqlash</button>
                            </form>
                        </div>

                        {{-- Tasdiqlash --}}
                        @if($visaInfo->status === 'pending')
                        <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm" x-data="{sr:false}">
                            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Tekshiruv</div>
                            @if($visaInfo->rejection_reason)
                                <div style="padding:8px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:11px;color:#991b1b;margin-bottom:8px;">{{ $visaInfo->rejection_reason }}</div>
                            @endif
                            <div style="display:flex;gap:6px;margin-bottom:6px;">
                                <form method="POST" action="{{ route('admin.international-students.approve', $student) }}">@csrf<button type="submit" style="padding:6px 16px;font-size:11px;font-weight:700;color:#fff;background:linear-gradient(135deg,#16a34a,#22c55e);border:none;border-radius:8px;cursor:pointer;" onclick="return confirm('Tasdiqlaysizmi?')">Tasdiqlash</button></form>
                                <button type="button" @click="sr=!sr" style="padding:6px 16px;font-size:11px;font-weight:700;color:#fff;background:#dc2626;border:none;border-radius:8px;cursor:pointer;">Rad etish</button>
                            </div>
                            <div x-show="sr" x-transition>
                                <form method="POST" action="{{ route('admin.international-students.reject', $student) }}">@csrf
                                    <textarea name="rejection_reason" rows="2" required style="width:100%;font-size:11px;padding:6px;border:1px solid #e2e8f0;border-radius:8px;outline:none;margin-bottom:6px;" placeholder="Sabab..."></textarea>
                                    <button type="submit" style="padding:5px 14px;font-size:11px;font-weight:700;color:#fff;background:#dc2626;border:none;border-radius:8px;cursor:pointer;">Yuborish</button>
                                </form>
                            </div>
                        </div>
                        @endif

                        {{-- Jarayon --}}
                        <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
                            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Jarayon</div>
                            @foreach([
                                ['l' => 'Registratsiya', 's' => $rps, 't' => 'registration', 'sl' => ['passport_accepted'=>'Pasport olindi','registering'=>'Qilinmoqda','done'=>'Tugallandi'], 'bl' => ['passport_accepted'=>'Qilinmoqda','registering'=>'Yangilandi']],
                                ['l' => 'Viza', 's' => $vps, 't' => 'visa', 'sl' => ['passport_accepted'=>'Pasport olindi','registering'=>'Yangilanmoqda','done'=>'Tugallandi'], 'bl' => ['passport_accepted'=>'Yangilanmoqda','registering'=>'Yangilandi']],
                            ] as $p)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;margin-bottom:6px;border-radius:8px;font-size:12px;{{ match($p['s']) { 'passport_accepted','registering' => 'background:#eff6ff;border:1px solid #bfdbfe;', 'done' => 'background:#f0fdf4;border:1px solid #bbf7d0;', default => 'background:#f8fafc;border:1px solid #e2e8f0;' } }}">
                                <div>
                                    <span style="font-weight:700;color:#334155;">{{ $p['l'] }}</span>
                                    <span style="margin-left:6px;padding:2px 8px;font-size:9px;font-weight:700;border-radius:10px;{{ match($p['s']) { 'passport_accepted' => 'background:#dbeafe;color:#1e40af;', 'registering' => 'background:#fef3c7;color:#92400e;', 'done' => 'background:#dcfce7;color:#166534;', default => 'background:#f1f5f9;color:#94a3b8;' } }}">{{ $p['sl'][$p['s']] ?? 'Kutilmoqda' }}</span>
                                </div>
                                @if($p['s'] === 'none' || $p['s'] === 'done')
                                    <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}"><button type="submit" style="padding:3px 10px;font-size:10px;font-weight:600;color:#fff;background:#2b5ea7;border:none;border-radius:6px;cursor:pointer;" onclick="return confirm('Pasportni qabul?')">Pasport olish</button></form>
                                @elseif($p['s'] === 'passport_accepted')
                                    <form method="POST" action="{{ route('admin.international-students.mark-registering', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}"><button type="submit" style="padding:3px 10px;font-size:10px;font-weight:600;color:#fff;background:#d97706;border:none;border-radius:6px;cursor:pointer;">{{ $p['bl']['passport_accepted'] }}</button></form>
                                @elseif($p['s'] === 'registering')
                                    <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}"><button type="submit" style="padding:3px 10px;font-size:10px;font-weight:600;color:#fff;background:#16a34a;border:none;border-radius:6px;cursor:pointer;" onclick="return confirm('Pasport qaytarish?')">{{ $p['bl']['registering'] }}</button></form>
                                @endif
                            </div>
                            @endforeach
                            <form method="POST" action="{{ route('admin.international-students.destroy-visa-info', $student) }}" style="margin-top:6px;">@csrf @method('DELETE')
                                <button type="submit" style="font-size:10px;color:#ef4444;background:none;border:none;cursor:pointer;font-weight:500;" onclick="return confirm('O\'chirasizmi?')">Ma'lumotlarni o'chirish</button>
                            </form>
                        </div>
                    </div>

                    {{-- O'NG: Ma'lumotlar --}}
                    <div class="lg:col-span-8">
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                            {{-- Pasport --}}
                            <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;">
                                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Pasport</div>
                                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                                    <div><span style="font-size:10px;color:#94a3b8;">Raqami</span><br><span style="font-size:15px;font-weight:800;color:#1e293b;">{{ $visaInfo->passport_number ?? '-' }}</span></div>
                                    <div><span style="font-size:10px;color:#94a3b8;">Berilgan joy</span><br><span style="font-size:13px;color:#475569;">{{ $visaInfo->passport_issued_place ?? '-' }}</span></div>
                                    <div><span style="font-size:10px;color:#94a3b8;">Berilgan sana</span><br><span style="font-size:13px;color:#475569;">{{ $visaInfo->passport_issued_date?->format('d.m.Y') ?? '-' }}</span></div>
                                    <div><span style="font-size:10px;color:#94a3b8;">Tugash</span><br><span style="font-size:13px;color:#475569;">{{ $visaInfo->passport_expiry_date?->format('d.m.Y') ?? '-' }}</span></div>
                                </div>
                            </div>

                            {{-- Reg + Viza yonma-yon --}}
                            <div style="display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #f1f5f9;">
                                <div style="padding:16px 20px;border-right:1px solid #f1f5f9;">
                                    <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Registratsiya</div>
                                    <table style="width:100%;font-size:12px;">
                                        <tr><td style="color:#94a3b8;padding:2px 0;">Boshlanish</td><td style="text-align:right;font-weight:600;color:#334155;">{{ $visaInfo->registration_start_date?->format('d.m.Y') ?? '-' }}</td></tr>
                                        <tr>
                                            <td style="color:#94a3b8;padding:2px 0;">Tugash</td>
                                            <td style="text-align:right;font-weight:600;color:#334155;">
                                                {{ $visaInfo->registration_end_date?->format('d.m.Y') ?? '-' }}
                                                @if($regDays !== null && $regDays <= 7)
                                                    <span style="margin-left:4px;padding:1px 6px;font-size:9px;font-weight:700;border-radius:4px;color:#fff;background:{{ $regDays <= 0 ? '#dc2626' : ($regDays <= 3 ? '#ef4444' : ($regDays <= 5 ? '#f59e0b' : '#22c55e')) }};">{{ $regDays <= 0 ? 'TUGAGAN' : $regDays.'k' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr><td style="color:#94a3b8;padding:2px 0;">Kirish sanasi</td><td style="text-align:right;font-weight:600;color:#334155;">{{ $visaInfo->entry_date?->format('d.m.Y') ?? '-' }}</td></tr>
                                    </table>
                                </div>
                                <div style="padding:16px 20px;">
                                    <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Viza</div>
                                    <table style="width:100%;font-size:12px;">
                                        <tr><td style="color:#94a3b8;padding:2px 0;">Raqami / Turi</td><td style="text-align:right;font-weight:700;color:#1e293b;">{{ $visaInfo->visa_number ?? '-' }} <span style="font-weight:400;color:#64748b;">{{ $visaInfo->visa_type }}</span></td></tr>
                                        <tr><td style="color:#94a3b8;padding:2px 0;">Kirishlar / Muddat</td><td style="text-align:right;color:#334155;">{{ $visaInfo->visa_entries_count ?? '-' }} marta · {{ $visaInfo->visa_stay_days ?? '-' }} kun</td></tr>
                                        <tr><td style="color:#94a3b8;padding:2px 0;">Boshlanish</td><td style="text-align:right;font-weight:600;color:#334155;">{{ $visaInfo->visa_start_date?->format('d.m.Y') ?? '-' }}</td></tr>
                                        <tr>
                                            <td style="color:#94a3b8;padding:2px 0;">Tugash</td>
                                            <td style="text-align:right;font-weight:600;color:#334155;">
                                                {{ $visaInfo->visa_end_date?->format('d.m.Y') ?? '-' }}
                                                @if($visaDays !== null && $visaDays <= 30)
                                                    <span style="margin-left:4px;padding:1px 6px;font-size:9px;font-weight:700;border-radius:4px;color:#fff;background:{{ $visaDays <= 0 ? '#dc2626' : ($visaDays <= 15 ? '#ef4444' : ($visaDays <= 20 ? '#f59e0b' : '#22c55e')) }};">{{ $visaDays <= 0 ? 'TUGAGAN' : $visaDays.'k' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr><td style="color:#94a3b8;padding:2px 0;">Berilgan</td><td style="text-align:right;color:#334155;">{{ $visaInfo->visa_issued_place ?? '-' }}, {{ $visaInfo->visa_issued_date?->format('d.m.Y') ?? '' }}</td></tr>
                                    </table>
                                </div>
                            </div>

                            {{-- Tug'ilgan joy + Hujjatlar --}}
                            <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                                <div>
                                    <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Tug'ilgan joy</div>
                                    <span style="font-size:13px;color:#334155;">{{ $visaInfo->birth_country ?? '-' }}, {{ $visaInfo->birth_region ?? '' }}, {{ $visaInfo->birth_city ?? '' }}</span>
                                </div>
                                <div>
                                    <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Hujjatlar</div>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        @foreach(['passport_scan_path' => ['Pasport','#2b5ea7','#eff6ff','#bfdbfe'], 'visa_scan_path' => ['Viza','#16a34a','#f0fdf4','#bbf7d0'], 'registration_doc_path' => ['Reg.','#d97706','#fffbeb','#fef3c7']] as $field => $cfg)
                                            @if($visaInfo->$field)
                                                <a href="{{ route('admin.international-students.file', [$student, $field]) }}" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;font-size:11px;font-weight:600;border-radius:6px;text-decoration:none;color:{{ $cfg[1] }};background:{{ $cfg[2] }};border:1px solid {{ $cfg[3] }};">
                                                    <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                    {{ $cfg[0] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

<style>
.btn-calc { display:inline-flex;align-items:center;gap:6px;padding:8px 20px;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 2px 8px rgba(43,94,167,0.3);white-space:nowrap; }
.btn-calc:hover { opacity:0.9;transform:translateY(-1px); }
</style>
</x-app-layout>
