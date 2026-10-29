<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.international-students.index') }}" style="color:#94a3b8;transition:color 0.15s;" onmouseover="this.style.color='#475569'" onmouseout="this.style.color='#94a3b8'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Xalqaro talabalar — sozlamalar</h2>
        </div>
    </x-slot>

    @php
        $basisLabels = \App\Models\StudentVisaInfo::BASIS_LABELS;
        $thresholdLabels = ['registration' => 'Registratsiya', 'visa' => 'Viza', 'residence_permit' => 'Yashash guvohnomasi'];
    @endphp

    <div style="padding:20px 0;">
        <div style="max-width:1000px;margin:0 auto;padding:0 16px;">
            @if(session('success'))
                <div style="margin-bottom:16px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#166534;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div style="margin-bottom:16px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:13px;color:#991b1b;">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.international-students.settings.update') }}">
                @csrf

                {{-- 1. Davlat bo'yicha turish asosi tanlovi --}}
                <div class="st-card">
                    <div class="st-title">Turish asosi tanlovi — davlatlar bo'yicha</div>
                    <p class="st-hint">
                        "Tanlov" belgilangan davlat talabalari formada <b>Viza bilan / Vizasiz / Yashash guvohnomasi</b> dan birini tanlaydi.
                        Belgilanmagan davlat talabalariga tanlov ko'rinmaydi — ularga "Standart asos" avtomatik qo'yiladi
                        (agar registrator talaba sahifasida boshqa asos qo'ygan bo'lsa, o'shanisi qoladi).
                        Davlat HEMIS dagi "Davlat" maydonidan olinadi.
                    </p>
                    <div style="overflow-x:auto;">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th>Davlat</th>
                                    <th style="text-align:center;">Talabalar</th>
                                    <th style="text-align:center;">Tanlov</th>
                                    <th>Standart asos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($countries as $i => $c)
                                    <tr>
                                        <td>
                                            {{ $c['name'] }}
                                            @unless($c['saved'])<span style="font-size:10px;color:#94a3b8;margin-left:4px;">(umumiy qoida)</span>@endunless
                                            <input type="hidden" name="countries[{{ $i }}][name]" value="{{ $c['name'] }}">
                                        </td>
                                        <td style="text-align:center;color:#64748b;">{{ $c['count'] }}</td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="countries[{{ $i }}][show_choice]" value="1" {{ old("countries.$i.show_choice", $c['show_choice']) ? 'checked' : '' }} style="accent-color:#4f46e5;width:16px;height:16px;cursor:pointer;">
                                        </td>
                                        <td>
                                            <select name="countries[{{ $i }}][default_basis]" class="st-input">
                                                @foreach($basisLabels as $k => $l)
                                                    <option value="{{ $k }}" {{ old("countries.$i.default_basis", $c['default_basis']) === $k ? 'selected' : '' }}>{{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr style="background:#f8fafc;">
                                    <td><b>Boshqa / noma'lum davlatlar</b><div style="font-size:10px;color:#94a3b8;">Ro'yxatda yo'q yoki HEMIS da davlati bo'sh talabalar</div></td>
                                    <td></td>
                                    <td style="text-align:center;">
                                        <input type="checkbox" name="fallback_show_choice" value="1" {{ old('fallback_show_choice', $fallback['show_choice']) ? 'checked' : '' }} style="accent-color:#4f46e5;width:16px;height:16px;cursor:pointer;">
                                    </td>
                                    <td>
                                        <select name="fallback_default_basis" class="st-input">
                                            @foreach($basisLabels as $k => $l)
                                                <option value="{{ $k }}" {{ old('fallback_default_basis', $fallback['default_basis']) === $k ? 'selected' : '' }}>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 2. Majburiy hujjatlar --}}
                <div class="st-card">
                    <div class="st-title">Majburiy hujjatlar (PDF)</div>
                    <p class="st-hint">Belgilanmagan hujjat formada ko'rinadi, lekin yuklash ixtiyoriy bo'ladi.</p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        @foreach(\App\Services\VisaSettings::DOCS_BY_BASIS as $basis => $docs)
                            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;">
                                <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:8px;">{{ $basisLabels[$basis] }}</div>
                                @foreach($docs as $doc)
                                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#475569;padding:3px 0;cursor:pointer;">
                                        <input type="checkbox" name="required_docs[{{ $basis }}][]" value="{{ $doc }}" {{ in_array($doc, old("required_docs.$basis", $requiredDocs[$basis]), true) ? 'checked' : '' }} style="accent-color:#4f46e5;">
                                        {{ \App\Services\VisaSettings::DOC_LABELS[$doc] }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- 3. Ogohlantirish muddatlari --}}
                <div class="st-card">
                    <div class="st-title">Ogohlantirish muddatlari (kun)</div>
                    <p class="st-hint">
                        Muddat tugashiga shuncha kun qolganda: <span style="color:#16a34a;font-weight:600;">yashil</span> — eslatma boshlanadi,
                        <span style="color:#d97706;font-weight:600;">sariq</span> — ogohlantirish,
                        <span style="color:#dc2626;font-weight:600;">qizil</span> — shoshilinch (pasport topshirish oynasi va "Shoshilinch xabar" tugmasi).
                        Talaba kabineti, ro'yxat, statistika va kunlik Telegram xabarlari shu qiymatlardan foydalanadi.
                    </p>
                    <table class="st-table" style="max-width:560px;">
                        <thead>
                            <tr>
                                <th></th>
                                <th style="text-align:center;color:#16a34a;">Yashil</th>
                                <th style="text-align:center;color:#d97706;">Sariq</th>
                                <th style="text-align:center;color:#dc2626;">Qizil</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($thresholds as $type => $t)
                                <tr>
                                    <td>{{ $thresholdLabels[$type] }}</td>
                                    @foreach(['info', 'warning', 'danger'] as $level)
                                        <td style="text-align:center;">
                                            <input type="number" min="1" max="365" name="thresholds[{{ $type }}][{{ $level }}]" value="{{ old("thresholds.$type.$level", $t[$level]) }}" class="st-input" style="width:80px;text-align:center;">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- 4. Ma'lumot kiritish muddati --}}
                <div class="st-card">
                    <div class="st-title">Yangi ma'lumot kiritish muddati</div>
                    <p class="st-hint">Registrator pasportni qaytargandan keyin talaba yangi viza/registratsiya ma'lumotini necha kun ichida kiritishi kerak. Muddat o'tsa, kabinet bloklanadi.</p>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="number" min="1" max="60" name="info_deadline_days" value="{{ old('info_deadline_days', $infoDeadlineDays) }}" class="st-input" style="width:100px;">
                        <span style="font-size:12px;color:#64748b;">kun</span>
                    </div>
                </div>

                {{-- 5. Ro'yxatlar --}}
                <div class="st-card">
                    <div class="st-title">Ro'yxatlar</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
                        <div>
                            <label class="st-label">Viza turlari</label>
                            <textarea name="visa_types" rows="8" class="st-input" style="width:100%;font-family:monospace;">{{ old('visa_types', $visaTypesText) }}</textarea>
                            <div class="st-hint" style="margin:4px 0 0;">Har bir qatorda bitta tur (20 belgigacha).</div>
                        </div>
                        <div>
                            <label class="st-label">Firmalar</label>
                            <textarea name="firms" rows="8" class="st-input" style="width:100%;font-family:monospace;">{{ old('firms', $firmLines) }}</textarea>
                            <div class="st-hint" style="margin:4px 0 0;">
                                Har bir qatorda: <code>KALIT | Ko'rinadigan nom</code> yoki faqat <code>KALIT</code>.
                                Kalit talabalar va firma javobgarlariga biriktirilgan — mavjud kalitni o'zgartirmang, faqat nomini o'zgartiring.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 6. Yotoqxona manzili --}}
                <div class="st-card">
                    <div class="st-title">Yotoqxona manzili</div>
                    <p class="st-hint">Talaba "Talabalar yotoqxonasi" ni tanlaganda shu manzil yoziladi (forma, eksport, talabnomalar).</p>
                    <textarea name="dormitory_address" rows="2" class="st-input" style="width:100%;">{{ old('dormitory_address', $dormitoryAddress) }}</textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px;">
                    <a href="{{ route('admin.international-students.index') }}" style="padding:9px 18px;font-size:13px;font-weight:600;color:#475569;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;">Bekor qilish</a>
                    <button type="submit" style="padding:9px 22px;font-size:13px;font-weight:600;background:#4f46e5;color:#fff;border:none;border-radius:8px;cursor:pointer;">Saqlash</button>
                </div>
            </form>
        </div>
    </div>

<style>
    .st-card { background:#fff;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.04);padding:18px 20px;margin-bottom:16px; }
    .st-title { font-size:13px;font-weight:700;color:#0f172a;margin-bottom:6px; }
    .st-hint { font-size:12px;color:#64748b;margin:0 0 12px;line-height:1.5; }
    .st-label { display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:4px; }
    .st-input { padding:6px 10px;font-size:12px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;color:#334155; }
    .st-input:focus { outline:none;border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
    .st-table { width:100%;border-collapse:collapse;font-size:12px; }
    .st-table th { text-align:left;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;padding:8px 10px;border-bottom:1px solid #e2e8f0; }
    .st-table td { padding:7px 10px;border-bottom:1px solid #f1f5f9;color:#334155; }
</style>
</x-app-layout>
