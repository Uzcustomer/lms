<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.international-students.show', $student) }}" style="color:#94a3b8;transition:color 0.15s;" onmouseover="this.style.color='#475569'" onmouseout="this.style.color='#94a3b8'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h2 style="font-weight:600;font-size:14px;color:#1e293b;">{{ $student->full_name }} — Tarix</h2>
        </div>
    </x-slot>

    @php
        $fieldLabels = [
            'birth_country' => "Tug'ilgan davlat",
            'birth_region' => "Tug'ilgan viloyat",
            'birth_city' => "Tug'ilgan shahar",
            'birth_date' => "Tug'ilgan sana",
            'passport_number' => 'Pasport raqami',
            'passport_issued_place' => 'Pasport berilgan joy',
            'passport_issued_date' => 'Pasport berilgan sana',
            'passport_expiry_date' => 'Pasport tugash sanasi',
            'passport_scan_path' => 'Pasport skaneri',
            'registration_start_date' => 'Registratsiya boshlanish',
            'registration_end_date' => 'Registratsiya tugash',
            'registration_doc_path' => 'Registratsiya hujjati',
            'registration_process_status' => 'Registratsiya jarayoni',
            'address_type' => 'Manzil turi',
            'current_address' => 'Joriy manzil',
            'visa_number' => 'Viza raqami',
            'visa_type' => 'Viza turi',
            'visa_start_date' => 'Viza boshlanish',
            'visa_end_date' => 'Viza tugash',
            'visa_issued_place' => 'Viza berilgan joy',
            'visa_issued_date' => 'Viza berilgan sana',
            'visa_entries_count' => 'Kirishlar soni',
            'visa_stay_days' => 'Istiqomat kunlari',
            'visa_scan_path' => 'Viza skaneri',
            'visa_process_status' => 'Viza jarayoni',
            'entry_date' => 'Chegaradan kirish',
            'firm' => 'Firma',
            'firm_custom' => 'Firma (boshqa)',
            'status' => 'Holati',
            'rejection_reason' => 'Rad etish sababi',
        ];

        $changeColors = [
            'created' => ['#4f46e5', '#eef2ff'],
            'updated' => ['#0891b2', '#ecfeff'],
            'approved' => ['#16a34a', '#f0fdf4'],
            'rejected' => ['#dc2626', '#fef2f2'],
            'passport_accepted' => ['#1e40af', '#dbeafe'],
            'mark_registering' => ['#d97706', '#fef3c7'],
            'passport_returned' => ['#7c3aed', '#f5f3ff'],
            'firm_assigned' => ['#0d9488', '#f0fdfa'],
            'deleted' => ['#991b1b', '#fee2e2'],
        ];
    @endphp

    <div style="padding:20px 0;">
        <div style="max-width:1100px;margin:0 auto;padding:0 16px;">
            <div class="hist-card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                <div class="hist-avatar">{{ mb_substr($student->full_name, 0, 1) }}</div>
                <div style="flex:1;">
                    <div style="font-weight:700;color:#0f172a;font-size:16px;">{{ $student->full_name }}</div>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;">
                        <span style="color:#4f46e5;font-weight:600;">{{ $student->group_name }}</span> · {{ $student->level_name }} · {{ $student->country_name ?? '-' }}
                    </div>
                </div>
                <div style="font-size:12px;color:#94a3b8;">Jami: <b style="color:#334155;">{{ $history->count() }}</b> ta yozuv</div>
            </div>

            @if($history->isEmpty())
                <div class="hist-card" style="padding:40px;text-align:center;color:#94a3b8;font-size:13px;">
                    Hozircha tarix bo'sh. Birinchi o'zgarishlar bu yerda ko'rinadi.
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($history as $h)
                        @php
                            [$color, $bg] = $changeColors[$h->change_type] ?? ['#64748b', '#f8fafc'];
                            $hasFile = $h->passport_scan_path || $h->visa_scan_path || $h->registration_doc_path;
                        @endphp
                        <div class="hist-card" style="overflow:hidden;" x-data="{open:false}">
                            <div @click="open=!open" style="padding:12px 16px;display:flex;align-items:center;gap:12px;cursor:pointer;border-left:3px solid {{ $color }};">
                                <div style="min-width:130px;">
                                    <div style="display:inline-block;padding:3px 10px;font-size:10px;font-weight:700;border-radius:10px;color:{{ $color }};background:{{ $bg }};text-transform:uppercase;letter-spacing:0.04em;">
                                        {{ $h->change_label }}
                                    </div>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:13px;color:#0f172a;font-weight:500;">
                                        {{ $h->actor_name ?? 'tizim' }}
                                        @if($h->actor_role)
                                            <span style="font-size:11px;color:#94a3b8;font-weight:400;">({{ $h->actor_role }})</span>
                                        @endif
                                    </div>
                                    @if($h->note)
                                        <div style="font-size:12px;color:#64748b;margin-top:2px;">{{ $h->note }}</div>
                                    @endif
                                    @if($h->changed_fields && count($h->changed_fields) > 0)
                                        <div style="font-size:11px;color:#0891b2;margin-top:3px;">
                                            O'zgargan:
                                            @foreach($h->changed_fields as $f)<span style="display:inline-block;padding:1px 6px;background:#ecfeff;border-radius:4px;margin:1px 2px;">{{ $fieldLabels[$f] ?? $f }}</span>@endforeach
                                        </div>
                                    @endif
                                </div>
                                <div style="text-align:right;font-size:11px;color:#94a3b8;white-space:nowrap;">
                                    <div style="font-weight:600;color:#475569;">{{ $h->created_at->format('d.m.Y') }}</div>
                                    <div>{{ $h->created_at->format('H:i') }}</div>
                                </div>
                                <span style="width:10px;color:#94a3b8;transition:transform 0.2s;" :style="open&&'transform:rotate(180deg)'">&#9660;</span>
                            </div>

                            <div x-show="open" x-cloak x-transition style="padding:14px 16px;border-top:1px solid #f1f5f9;background:#fafbfc;">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12px;">
                                    <div>
                                        <div class="hist-section">Pasport</div>
                                        <div class="hist-kv"><span>Raqami</span><span>{{ $h->passport_number ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Berilgan</span><span>{{ $h->passport_issued_place ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Berilgan sana</span><span>{{ $h->passport_issued_date?->format('d.m.Y') ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Tugash</span><span>{{ $h->passport_expiry_date?->format('d.m.Y') ?? '—' }}</span></div>
                                    </div>
                                    <div>
                                        <div class="hist-section">Tug'ilgan joy</div>
                                        <div class="hist-kv"><span>Davlat</span><span>{{ $h->birth_country ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Viloyat</span><span>{{ $h->birth_region ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Shahar</span><span>{{ $h->birth_city ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Sana</span><span>{{ $h->birth_date?->format('d.m.Y') ?? '—' }}</span></div>
                                    </div>
                                    <div>
                                        <div class="hist-section">Registratsiya</div>
                                        <div class="hist-kv"><span>Boshlanish</span><span>{{ $h->registration_start_date?->format('d.m.Y') ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Tugash</span><span>{{ $h->registration_end_date?->format('d.m.Y') ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Jarayon</span><span>{{ $h->registration_process_status ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Manzil turi</span><span>{{ $h->address_type ?? '—' }}</span></div>
                                    </div>
                                    <div>
                                        <div class="hist-section">Viza</div>
                                        <div class="hist-kv"><span>Raqami</span><span>{{ $h->visa_number ?? '—' }} {{ $h->visa_type ? '('.$h->visa_type.')' : '' }}</span></div>
                                        <div class="hist-kv"><span>Boshlanish</span><span>{{ $h->visa_start_date?->format('d.m.Y') ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Tugash</span><span>{{ $h->visa_end_date?->format('d.m.Y') ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Berilgan</span><span>{{ $h->visa_issued_place ?? '—' }} {{ $h->visa_issued_date ? $h->visa_issued_date->format('d.m.Y') : '' }}</span></div>
                                        <div class="hist-kv"><span>Kirishlar</span><span>{{ $h->visa_entries_count ?? '—' }} marta · {{ $h->visa_stay_days ?? '—' }} kun</span></div>
                                    </div>
                                    <div>
                                        <div class="hist-section">Boshqa</div>
                                        <div class="hist-kv"><span>Chegaradan kirish</span><span>{{ $h->entry_date?->format('d.m.Y') ?? '—' }}</span></div>
                                        <div class="hist-kv"><span>Firma</span><span>{{ $h->firm ?? '—' }}{{ $h->firm_custom ? ' · '.$h->firm_custom : '' }}</span></div>
                                        <div class="hist-kv"><span>Holati</span><span>{{ $h->status ?? '—' }}</span></div>
                                        @if($h->rejection_reason)
                                            <div class="hist-kv"><span>Rad sababi</span><span style="color:#991b1b;">{{ $h->rejection_reason }}</span></div>
                                        @endif
                                    </div>
                                    @if($hasFile)
                                        <div>
                                            <div class="hist-section">Saqlangan PDF lar</div>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
                                                @foreach(['passport_scan_path'=>['Pasport','#4f46e5','#eef2ff','#c7d2fe'],'visa_scan_path'=>['Viza','#059669','#ecfdf5','#a7f3d0'],'registration_doc_path'=>['Reg.','#d97706','#fffbeb','#fde68a']] as $f=>$c)
                                                    @if($h->$f)
                                                        <a href="{{ route('admin.international-students.history-file', [$student, $h->id, $f]) }}" target="_blank" class="hist-file-btn" style="color:{{ $c[1] }};background:{{ $c[2] }};border-color:{{ $c[3] }};">
                                                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                            {{ $c[0] }}
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .hist-card { background:#fff;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.04); }
        .hist-avatar { width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#6366f1);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;flex-shrink:0; }
        .hist-section { font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px; }
        .hist-kv { display:flex;justify-content:space-between;gap:10px;padding:4px 0;border-bottom:1px solid #f1f5f9; }
        .hist-kv:last-child { border-bottom:none; }
        .hist-kv > span:first-child { color:#94a3b8; }
        .hist-kv > span:last-child { color:#334155;font-weight:500;text-align:right; }
        .hist-file-btn { display:inline-flex;align-items:center;gap:5px;padding:6px 12px;font-size:11px;font-weight:600;border-radius:6px;text-decoration:none;border:1px solid;transition:all 0.15s; }
        .hist-file-btn:hover { transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,0.08); }
    </style>
</x-app-layout>
