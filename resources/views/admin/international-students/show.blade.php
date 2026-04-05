<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.international-students.index') }}" style="color:#94a3b8;transition:color 0.15s;" onmouseover="this.style.color='#475569'" onmouseout="this.style.color='#94a3b8'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h2 style="font-weight:600;font-size:14px;color:#1e293b;">{{ $student->full_name }}</h2>
        </div>
    </x-slot>

    <div style="padding:20px 0;">
        <div style="max-width:1100px;margin:0 auto;padding:0 16px;">
            @if(session('success'))
                <div style="margin-bottom:16px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#166534;">{{ session('success') }}</div>
            @endif

            @if(!$visaInfo)
                <div class="sv-card" style="padding:20px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                        <div class="sv-avatar">{{ mb_substr($student->full_name, 0, 1) }}</div>
                        <div>
                            <div style="font-weight:700;color:#1e293b;">{{ $student->full_name }}</div>
                            <div style="font-size:12px;color:#94a3b8;">{{ $student->group_name }} · {{ $student->level_name }} · {{ $student->country_name ?? '' }}@if($student->phone) · {{ $student->phone }}@endif @if($student->telegram_username) · <span style="color:#0088cc;">@{{ $student->telegram_username }}</span>@endif</div>
                        </div>
                    </div>
                    <div style="padding:12px;background:#fefce8;border:1px solid #fef08a;border-radius:8px;margin-bottom:16px;font-size:12px;color:#854d0e;">Talaba hali viza ma'lumotlarini kiritmagan. Siz to'ldirishingiz mumkin.</div>
                    @include('admin.international-students._visa-form', ['student' => $student, 'visaInfo' => null])
                </div>
            @else
                @php
                    $rps = $visaInfo->registration_process_status ?? 'none';
                    $vps = $visaInfo->visa_process_status ?? 'none';
                    $regDays = $visaInfo->registrationDaysLeft();
                    $visaDays = $visaInfo->visaDaysLeft();
                @endphp

                {{-- Profile Header --}}
                <div class="sv-card" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:14px;">
                        <div class="sv-avatar">{{ mb_substr($student->full_name, 0, 1) }}</div>
                        <div>
                            <div style="font-weight:700;color:#0f172a;font-size:16px;letter-spacing:-0.01em;">{{ $student->full_name }}</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">
                                <span style="color:#4f46e5;font-weight:600;">{{ $student->group_name }}</span> · {{ $student->level_name }} · <span style="font-weight:600;">{{ $student->country_name ?? '-' }}</span> · {{ $student->department_name }}
                            </div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:3px;display:flex;gap:12px;flex-wrap:wrap;">
                                @if($student->phone)
                                    <span style="display:inline-flex;align-items:center;gap:3px;"><svg style="width:12px;height:12px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg> {{ $student->phone }}</span>
                                @endif
                                @if($student->telegram_username)
                                    <span style="display:inline-flex;align-items:center;gap:3px;color:#0088cc;"><svg style="width:12px;height:12px;" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg> @{{ $student->telegram_username }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        @if($visaInfo->status === 'approved')
                            <span class="sv-badge sv-badge-green">Tasdiqlangan</span>
                        @elseif($visaInfo->status === 'rejected')
                            <span class="sv-badge sv-badge-red">Rad etilgan</span>
                        @else
                            <span class="sv-badge sv-badge-amber">Tekshirilmoqda</span>
                        @endif
                        @if($visaInfo->firm)
                            <span class="sv-badge sv-badge-indigo">{{ $visaInfo->firm_display }}</span>
                        @endif
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:340px 1fr;gap:16px;" class="sv-grid">
                    {{-- LEFT PANEL --}}
                    <div style="display:flex;flex-direction:column;gap:12px;">

                        {{-- Firma --}}
                        <div class="sv-card" style="padding:16px;">
                            <div class="sv-section-title">Firma biriktirish</div>
                            <form method="POST" action="{{ route('admin.international-students.assign-firm', $student) }}" style="display:flex;gap:8px;align-items:center;">
                                @csrf
                                <select name="firm" class="sv-select" style="flex:1;" onchange="this.form.querySelector('.fo').style.display=this.value==='other'?'block':'none'">
                                    <option value="">Tanlang</option>
                                    @foreach(\App\Models\StudentVisaInfo::FIRM_OPTIONS as $key => $label)
                                        <option value="{{ $key }}" {{ $visaInfo->firm === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                    <option value="other" {{ $visaInfo->firm === 'other' ? 'selected' : '' }}>Boshqa</option>
                                </select>
                                <button type="submit" class="sv-btn sv-btn-indigo">Saqlash</button>
                            </form>
                        </div>

                        {{-- Tekshiruv --}}
                        @if($visaInfo->status === 'pending')
                        <div class="sv-card" style="padding:16px;" x-data="{sr:false}">
                            <div class="sv-section-title">Tekshiruv</div>
                            @if($visaInfo->rejection_reason)
                                <div style="padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;color:#991b1b;margin-bottom:10px;">{{ $visaInfo->rejection_reason }}</div>
                            @endif
                            <div style="display:flex;gap:8px;">
                                <form method="POST" action="{{ route('admin.international-students.approve', $student) }}">@csrf<button type="submit" class="sv-btn sv-btn-green" onclick="return confirm('Tasdiqlaysizmi?')">Tasdiqlash</button></form>
                                <button type="button" @click="sr=!sr" class="sv-btn sv-btn-red">Rad etish</button>
                            </div>
                            <div x-show="sr" x-transition style="margin-top:10px;">
                                <form method="POST" action="{{ route('admin.international-students.reject', $student) }}">@csrf
                                    <textarea name="rejection_reason" rows="2" required class="sv-select" style="width:100%;margin-bottom:8px;resize:vertical;" placeholder="Sabab..."></textarea>
                                    <button type="submit" class="sv-btn sv-btn-red">Yuborish</button>
                                </form>
                            </div>
                        </div>
                        @endif

                        {{-- Jarayon --}}
                        <div class="sv-card" style="padding:16px;">
                            <div class="sv-section-title">Jarayon</div>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach([
                                    ['l'=>'Registratsiya','s'=>$rps,'t'=>'registration','sl'=>['passport_accepted'=>'Pasport olindi','registering'=>'Qilinmoqda','done'=>'Tugallandi'],'btn'=>['passport_accepted'=>'Qilinmoqda','registering'=>'Yangilandi']],
                                    ['l'=>'Viza','s'=>$vps,'t'=>'visa','sl'=>['passport_accepted'=>'Pasport olindi','registering'=>'Yangilanmoqda','done'=>'Tugallandi'],'btn'=>['passport_accepted'=>'Yangilanmoqda','registering'=>'Yangilandi']],
                                ] as $p)
                                <div class="sv-process-row {{ $p['s'] !== 'none' && $p['s'] !== 'done' ? 'sv-process-active' : ($p['s'] === 'done' ? 'sv-process-done' : '') }}">
                                    <div style="display:flex;align-items:center;gap:8px;flex:1;">
                                        <span style="font-weight:600;font-size:13px;color:#334155;">{{ $p['l'] }}</span>
                                        <span class="sv-status-dot {{ match($p['s']) { 'passport_accepted'=>'sv-dot-blue','registering'=>'sv-dot-amber','done'=>'sv-dot-green', default=>'sv-dot-gray' } }}">{{ $p['sl'][$p['s']] ?? 'Kutilmoqda' }}</span>
                                    </div>
                                    @if($p['s']==='none'||$p['s']==='done')
                                        <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}"><button type="submit" class="sv-btn sv-btn-indigo sv-btn-sm" onclick="return confirm('Pasportni qabul?')">Pasport olish</button></form>
                                    @elseif($p['s']==='passport_accepted')
                                        <form method="POST" action="{{ route('admin.international-students.mark-registering', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}"><button type="submit" class="sv-btn sv-btn-amber sv-btn-sm">{{ $p['btn']['passport_accepted'] }}</button></form>
                                    @elseif($p['s']==='registering')
                                        <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}"><button type="submit" class="sv-btn sv-btn-green sv-btn-sm" onclick="return confirm('Pasport qaytarish?')">{{ $p['btn']['registering'] }}</button></form>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            <div style="margin-top:12px;padding-top:10px;border-top:1px solid #f1f5f9;">
                                <form method="POST" action="{{ route('admin.international-students.destroy-visa-info', $student) }}">@csrf @method('DELETE')
                                    <button type="submit" style="font-size:11px;color:#ef4444;background:none;border:none;cursor:pointer;opacity:0.7;transition:opacity 0.15s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'" onclick="return confirm('O\'chirasizmi?')">Ma'lumotlarni o'chirish</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT PANEL --}}
                    <div class="sv-card" style="padding:0;overflow:hidden;">
                        {{-- Pasport --}}
                        <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;">
                            <div class="sv-section-title" style="margin-bottom:14px;">Pasport</div>
                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                                <div class="sv-field">
                                    <div class="sv-field-label">Raqami</div>
                                    <div style="font-size:18px;font-weight:800;color:#0f172a;letter-spacing:0.02em;">{{ $visaInfo->passport_number ?? '—' }}</div>
                                </div>
                                <div class="sv-field">
                                    <div class="sv-field-label">Berilgan joy</div>
                                    <div class="sv-field-value">{{ $visaInfo->passport_issued_place ?? '—' }}</div>
                                </div>
                                <div class="sv-field">
                                    <div class="sv-field-label">Berilgan sana</div>
                                    <div class="sv-field-value">{{ $visaInfo->passport_issued_date?->format('d.m.Y') ?? '—' }}</div>
                                </div>
                                <div class="sv-field">
                                    <div class="sv-field-label">Amal qilish</div>
                                    <div class="sv-field-value">{{ $visaInfo->passport_expiry_date?->format('d.m.Y') ?? '—' }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Reg + Viza --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;">
                            <div style="padding:20px 24px;border-right:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;">
                                <div class="sv-section-title" style="margin-bottom:12px;">Registratsiya</div>
                                <div class="sv-kv"><span>Boshlanish</span><span>{{ $visaInfo->registration_start_date?->format('d.m.Y') ?? '—' }}</span></div>
                                <div class="sv-kv">
                                    <span>Tugash</span>
                                    <span>
                                        {{ $visaInfo->registration_end_date?->format('d.m.Y') ?? '—' }}
                                        @if($regDays !== null && $regDays <= 7)<span class="sv-days-badge" style="background:{{ $regDays <= 0 ? '#dc2626' : ($regDays <= 3 ? '#ef4444' : ($regDays <= 5 ? '#f59e0b' : '#22c55e')) }};">{{ $regDays <= 0 ? 'TUGAGAN' : $regDays.'k' }}</span>@endif
                                    </span>
                                </div>
                                <div class="sv-kv"><span>Kirish sanasi</span><span>{{ $visaInfo->entry_date?->format('d.m.Y') ?? '—' }}</span></div>
                            </div>
                            <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;">
                                <div class="sv-section-title" style="margin-bottom:12px;">Viza</div>
                                <div class="sv-kv"><span>Raqami</span><span style="font-weight:700;">{{ $visaInfo->visa_number ?? '—' }} <span style="font-weight:400;color:#94a3b8;">{{ $visaInfo->visa_type }}</span></span></div>
                                <div class="sv-kv"><span>Kirishlar</span><span>{{ $visaInfo->visa_entries_count ?? '—' }} marta · {{ $visaInfo->visa_stay_days ?? '—' }} kun</span></div>
                                <div class="sv-kv"><span>Boshlanish</span><span>{{ $visaInfo->visa_start_date?->format('d.m.Y') ?? '—' }}</span></div>
                                <div class="sv-kv">
                                    <span>Tugash</span>
                                    <span>
                                        {{ $visaInfo->visa_end_date?->format('d.m.Y') ?? '—' }}
                                        @if($visaDays !== null && $visaDays <= 30)<span class="sv-days-badge" style="background:{{ $visaDays <= 0 ? '#dc2626' : ($visaDays <= 15 ? '#ef4444' : ($visaDays <= 20 ? '#f59e0b' : '#22c55e')) }};">{{ $visaDays <= 0 ? 'TUGAGAN' : $visaDays.'k' }}</span>@endif
                                    </span>
                                </div>
                                <div class="sv-kv"><span>Berilgan</span><span>{{ $visaInfo->visa_issued_place ?? '—' }}{{ $visaInfo->visa_issued_date ? ', '.$visaInfo->visa_issued_date->format('d.m.Y') : '' }}</span></div>
                            </div>
                        </div>

                        {{-- Bottom --}}
                        <div style="padding:16px 24px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                            <div>
                                <div class="sv-section-title" style="margin-bottom:6px;">Tug'ilgan joy</div>
                                <div style="font-size:13px;color:#334155;">{{ $visaInfo->birth_country ?? '—' }}{{ $visaInfo->birth_region ? ', '.$visaInfo->birth_region : '' }}{{ $visaInfo->birth_city ? ', '.$visaInfo->birth_city : '' }}</div>
                            </div>
                            <div>
                                <div class="sv-section-title" style="margin-bottom:6px;">Hujjatlar</div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    @foreach(['passport_scan_path'=>['Pasport','#4f46e5','#eef2ff','#c7d2fe'],'visa_scan_path'=>['Viza','#059669','#ecfdf5','#a7f3d0'],'registration_doc_path'=>['Reg.','#d97706','#fffbeb','#fde68a']] as $f=>$c)
                                        @if($visaInfo->$f)
                                            <a href="{{ route('admin.international-students.file', [$student, $f]) }}" target="_blank" class="sv-file-btn" style="color:{{ $c[1] }};background:{{ $c[2] }};border-color:{{ $c[3] }};">
                                                <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                {{ $c[0] }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tahrirlash --}}
                <div style="margin-top:16px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;overflow:hidden;" x-data="{editOpen:false}">
                    <div @click="editOpen=!editOpen" style="padding:8px 16px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <svg style="width:12px;height:12px;min-width:12px;min-height:12px;max-width:12px;max-height:12px;color:#4f46e5;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        <span style="font-size:12px;font-weight:600;color:#4f46e5;flex:1;">Tahrirlash</span>
                        <span style="width:10px;height:10px;min-width:10px;display:inline-block;color:#94a3b8;transition:transform 0.2s;" :style="editOpen&&'transform:rotate(180deg)'">&#9660;</span>
                    </div>
                    <div x-show="editOpen" x-transition style="padding:0 16px 14px;border-top:1px solid #f1f5f9;">
                        @include('admin.international-students._visa-form', ['student' => $student, 'visaInfo' => $visaInfo])
                    </div>
                </div>
            @endif
        </div>
    </div>

<style>
    .sv-card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.04),0 1px 2px rgba(0,0,0,0.02); }
    .sv-avatar { width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#6366f1);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;flex-shrink:0; }
    .sv-badge { padding:4px 12px;font-size:11px;font-weight:700;border-radius:20px;white-space:nowrap; }
    .sv-badge-green { background:#dcfce7;color:#166534;border:1px solid #bbf7d0; }
    .sv-badge-red { background:#fef2f2;color:#991b1b;border:1px solid #fecaca; }
    .sv-badge-amber { background:#fefce8;color:#854d0e;border:1px solid #fef08a; }
    .sv-badge-indigo { background:#4f46e5;color:#fff;border:1px solid #4338ca; }
    .sv-section-title { font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.08em; }
    .sv-select { padding:8px 12px;font-size:12px;border:1px solid #e2e8f0;border-radius:8px;outline:none;background:#fff;color:#334155;transition:border-color 0.15s; }
    .sv-select:focus { border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
    .sv-btn { padding:8px 16px;font-size:12px;font-weight:600;border:none;border-radius:8px;cursor:pointer;transition:all 0.15s;white-space:nowrap; }
    .sv-btn:hover { transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.12); }
    .sv-btn-sm { padding:5px 12px;font-size:11px;border-radius:6px; }
    .sv-btn-indigo { background:#4f46e5;color:#fff; }
    .sv-btn-indigo:hover { background:#4338ca; }
    .sv-btn-green { background:#16a34a;color:#fff; }
    .sv-btn-green:hover { background:#15803d; }
    .sv-btn-red { background:#dc2626;color:#fff; }
    .sv-btn-red:hover { background:#b91c1c; }
    .sv-btn-amber { background:#d97706;color:#fff; }
    .sv-btn-amber:hover { background:#b45309; }
    .sv-process-row { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;transition:all 0.15s; }
    .sv-process-active { background:#eef2ff;border-color:#c7d2fe; }
    .sv-process-done { background:#f0fdf4;border-color:#bbf7d0; }
    .sv-status-dot { padding:2px 10px;font-size:10px;font-weight:700;border-radius:10px; }
    .sv-dot-blue { background:#dbeafe;color:#1e40af; }
    .sv-dot-amber { background:#fef3c7;color:#92400e; }
    .sv-dot-green { background:#dcfce7;color:#166534; }
    .sv-dot-gray { background:#f1f5f9;color:#94a3b8; }
    .sv-field-label { font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px; }
    .sv-field-value { font-size:14px;color:#334155;font-weight:500; }
    .sv-kv { display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:12px; }
    .sv-kv:last-child { border-bottom:none; }
    .sv-kv > span:first-child { color:#94a3b8; }
    .sv-kv > span:last-child { color:#334155;font-weight:500;text-align:right; }
    .sv-days-badge { margin-left:6px;padding:1px 6px;font-size:9px;font-weight:800;border-radius:4px;color:#fff;display:inline-block; }
    .sv-file-btn { display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:11px;font-weight:600;border-radius:6px;text-decoration:none;border:1px solid;transition:all 0.15s; }
    .sv-file-btn:hover { transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,0.08); }
    @media(max-width:768px) { .sv-grid { grid-template-columns:1fr !important; } }
</style>
</x-app-layout>
