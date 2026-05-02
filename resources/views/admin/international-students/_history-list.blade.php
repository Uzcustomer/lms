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

@if($history->isEmpty())
    <div style="padding:24px;text-align:center;color:#94a3b8;font-size:12px;">
        Hozircha tarix bo'sh. Keyingi o'zgarishlar bu yerda ko'rinadi.
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:8px;">
        @foreach($history as $h)
            @php
                [$color, $bg] = $changeColors[$h->change_type] ?? ['#64748b', '#f1f5f9'];
                $hasFile = $h->passport_scan_path || $h->visa_scan_path || $h->registration_doc_path;
            @endphp
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;" x-data="{rowOpen:false}">
                <div @click="rowOpen=!rowOpen" style="padding:10px 12px;display:flex;align-items:center;gap:10px;cursor:pointer;border-left:3px solid {{ $color }};">
                    <div style="min-width:120px;">
                        <span style="display:inline-block;padding:3px 9px;font-size:10px;font-weight:700;border-radius:10px;color:{{ $color }};background:{{ $bg }};text-transform:uppercase;letter-spacing:0.04em;">{{ $h->change_label }}</span>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:12px;color:#0f172a;font-weight:500;">
                            {{ $h->actor_name ?? 'tizim' }}@if($h->actor_role)<span style="font-size:10px;color:#94a3b8;font-weight:400;"> ({{ $h->actor_role }})</span>@endif
                        </div>
                        @if($h->note)
                            <div style="font-size:11px;color:#64748b;margin-top:1px;">{{ $h->note }}</div>
                        @endif
                        @if($h->changed_fields && count($h->changed_fields) > 0)
                            <div style="font-size:10px;color:#0891b2;margin-top:2px;line-height:1.6;">
                                @foreach($h->changed_fields as $f)<span style="display:inline-block;padding:1px 5px;background:#ecfeff;border-radius:3px;margin:0 2px;">{{ $fieldLabels[$f] ?? $f }}</span>@endforeach
                            </div>
                        @endif
                    </div>
                    <div style="text-align:right;font-size:10px;color:#94a3b8;white-space:nowrap;">
                        <div style="font-weight:600;color:#475569;font-size:11px;">{{ $h->created_at->format('d.m.Y') }}</div>
                        <div>{{ $h->created_at->format('H:i') }}</div>
                    </div>
                    <span style="width:10px;color:#cbd5e1;transition:transform 0.2s;" :style="rowOpen&&'transform:rotate(180deg)'">&#9660;</span>
                </div>

                <div x-show="rowOpen" x-cloak x-transition style="padding:12px;border-top:1px solid #f1f5f9;background:#fafbfc;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:11px;">
                        <div>
                            <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:5px;">Pasport</div>
                            <div class="hist-kv-mini"><span>Raqami</span><span>{{ $h->passport_number ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Berilgan joy</span><span>{{ $h->passport_issued_place ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Berilgan</span><span>{{ $h->passport_issued_date?->format('d.m.Y') ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Tugash</span><span>{{ $h->passport_expiry_date?->format('d.m.Y') ?? '—' }}</span></div>
                        </div>
                        <div>
                            <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:5px;">Tug'ilgan joy</div>
                            <div class="hist-kv-mini"><span>Davlat</span><span>{{ $h->birth_country ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Viloyat</span><span>{{ $h->birth_region ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Shahar</span><span>{{ $h->birth_city ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Sana</span><span>{{ $h->birth_date?->format('d.m.Y') ?? '—' }}</span></div>
                        </div>
                        <div>
                            <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:5px;">Registratsiya</div>
                            <div class="hist-kv-mini"><span>Boshlanish</span><span>{{ $h->registration_start_date?->format('d.m.Y') ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Tugash</span><span>{{ $h->registration_end_date?->format('d.m.Y') ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Jarayon</span><span>{{ $h->registration_process_status ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Manzil</span><span>{{ $h->address_type ?? '—' }}</span></div>
                        </div>
                        <div>
                            <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:5px;">Viza</div>
                            <div class="hist-kv-mini"><span>Raqami</span><span>{{ $h->visa_number ?? '—' }} {{ $h->visa_type ? '('.$h->visa_type.')' : '' }}</span></div>
                            <div class="hist-kv-mini"><span>Boshlanish</span><span>{{ $h->visa_start_date?->format('d.m.Y') ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Tugash</span><span>{{ $h->visa_end_date?->format('d.m.Y') ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Berilgan</span><span>{{ $h->visa_issued_place ?? '—' }}{{ $h->visa_issued_date ? ' · '.$h->visa_issued_date->format('d.m.Y') : '' }}</span></div>
                            <div class="hist-kv-mini"><span>Kirishlar</span><span>{{ $h->visa_entries_count ?? '—' }} marta · {{ $h->visa_stay_days ?? '—' }} kun</span></div>
                        </div>
                        <div>
                            <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:5px;">Boshqa</div>
                            <div class="hist-kv-mini"><span>Chegaradan</span><span>{{ $h->entry_date?->format('d.m.Y') ?? '—' }}</span></div>
                            <div class="hist-kv-mini"><span>Firma</span><span>{{ $h->firm ?? '—' }}{{ $h->firm_custom ? ' · '.$h->firm_custom : '' }}</span></div>
                            <div class="hist-kv-mini"><span>Holati</span><span>{{ $h->status ?? '—' }}</span></div>
                            @if($h->rejection_reason)
                                <div class="hist-kv-mini"><span>Rad sababi</span><span style="color:#991b1b;">{{ $h->rejection_reason }}</span></div>
                            @endif
                        </div>
                        @if($hasFile)
                            <div>
                                <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:5px;">Saqlangan PDF lar</div>
                                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                    @foreach(['passport_scan_path'=>['Pasport','#4f46e5','#eef2ff','#c7d2fe'],'visa_scan_path'=>['Viza','#059669','#ecfdf5','#a7f3d0'],'registration_doc_path'=>['Reg.','#d97706','#fffbeb','#fde68a']] as $f=>$c)
                                        @if($h->$f)
                                            <a href="{{ route('admin.international-students.history-file', [$student, $h->id, $f]) }}" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;font-size:10px;font-weight:600;border-radius:5px;text-decoration:none;border:1px solid {{ $c[3] }};color:{{ $c[1] }};background:{{ $c[2] }};">
                                                <svg style="width:11px;height:11px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
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

<style>
    .hist-kv-mini { display:flex;justify-content:space-between;gap:8px;padding:3px 0;border-bottom:1px dashed #f1f5f9;font-size:11px; }
    .hist-kv-mini:last-child { border-bottom:none; }
    .hist-kv-mini > span:first-child { color:#94a3b8; }
    .hist-kv-mini > span:last-child { color:#334155;font-weight:500;text-align:right; }
</style>
