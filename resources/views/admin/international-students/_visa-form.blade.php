<form method="POST" action="{{ route('admin.international-students.store-visa', $student) }}">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport raqami</label>
            <input type="text" name="passport_number" value="{{ $visaInfo?->passport_number ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport berilgan joy</label>
            <input type="text" name="passport_issued_place" value="{{ $visaInfo?->passport_issued_place ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport berilgan sana</label>
            <input type="date" name="passport_issued_date" value="{{ $visaInfo?->passport_issued_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Pasport tugash sanasi</label>
            <input type="date" name="passport_expiry_date" value="{{ $visaInfo?->passport_expiry_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;color:#94a3b8;margin:14px 0 8px;text-transform:uppercase;">Tug'ilgan joy</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Davlat</label>
            <input type="text" name="birth_country" value="{{ $visaInfo?->birth_country ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Viloyat</label>
            <input type="text" name="birth_region" value="{{ $visaInfo?->birth_region ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Shahar</label>
            <input type="text" name="birth_city" value="{{ $visaInfo?->birth_city ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;color:#94a3b8;margin:14px 0 8px;text-transform:uppercase;">Registratsiya</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Boshlanish</label>
            <input type="date" name="registration_start_date" value="{{ $visaInfo?->registration_start_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Tugash</label>
            <input type="date" name="registration_end_date" value="{{ $visaInfo?->registration_end_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Kirish sanasi</label>
            <input type="date" name="entry_date" value="{{ $visaInfo?->entry_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;color:#94a3b8;margin:14px 0 8px;text-transform:uppercase;">Viza</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Viza raqami</label>
            <input type="text" name="visa_number" value="{{ $visaInfo?->visa_number ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Viza turi</label>
            <select name="visa_type" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
                <option value="">-</option>
                @foreach(\App\Models\StudentVisaInfo::VISA_TYPES as $k => $l)
                    <option value="{{ $k }}" {{ ($visaInfo?->visa_type ?? '') === $k ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Kirishlar</label>
            <input type="number" name="visa_entries_count" value="{{ $visaInfo?->visa_entries_count ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Muddat (kun)</label>
            <input type="number" name="visa_stay_days" value="{{ $visaInfo?->visa_stay_days ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-top:10px;">
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Boshlanish</label>
            <input type="date" name="visa_start_date" value="{{ $visaInfo?->visa_start_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Tugash</label>
            <input type="date" name="visa_end_date" value="{{ $visaInfo?->visa_end_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Berilgan joy</label>
            <input type="text" name="visa_issued_place" value="{{ $visaInfo?->visa_issued_place ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
        <div>
            <label style="font-size:11px;color:#64748b;display:block;margin-bottom:3px;">Berilgan sana</label>
            <input type="date" name="visa_issued_date" value="{{ $visaInfo?->visa_issued_date?->format('Y-m-d') ?? '' }}" style="width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">
        </div>
    </div>

    <div style="margin-top:14px;display:flex;justify-content:flex-end;">
        <button type="submit" style="padding:8px 20px;font-size:12px;font-weight:600;background:#4f46e5;color:#fff;border:none;border-radius:8px;cursor:pointer;">Saqlash</button>
    </div>
</form>
