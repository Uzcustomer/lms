{{-- Info banner --}}
<div style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid #93c5fd;">
    <div style="display: flex; gap: 14px;">
        <div style="width: 40px; height: 40px; background: rgba(37,99,235,0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <svg style="width: 22px; height: 22px; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div>
            <div style="font-size: 14px; font-weight: 600; color: #1e40af; margin-bottom: 8px;">Parolni tiklash jarayoni</div>
            <div style="font-size: 13px; color: #1e40af; line-height: 1.6;">
                1. Admin talabaning parolini tiklaydi (vaqtinchalik parol = talaba ID raqami)<br>
                2. Talaba vaqtinchalik parol bilan tizimga kiradi<br>
                3. Tizim talabani yangi parol o'rnatishga majbur qiladi<br>
                4. Yangi parol ham muddatli bo'ladi — talaba HEMIS parolini tiklab olguncha foydalanadi
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.settings.update.password') }}">
    @csrf

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px;">
        {{-- Vaqtinchalik parol --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 24px; height: 24px; color: #d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #111827;">Vaqtinchalik parol</div>
                    <div style="font-size: 12px; color: #6b7280;">Admin tiklaganidan keyin</div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="number" name="temp_password_days" id="temp_password_days" min="1" max="365"
                       value="{{ old('temp_password_days', $tempPasswordDays) }}"
                       style="width: 100px; padding: 10px 14px; border: 2px solid #fbbf24; border-radius: 10px; font-size: 22px; font-weight: 700; color: #92400e; text-align: center; background: #fffbeb;">
                <span style="font-size: 16px; color: #92400e; font-weight: 600;">kun</span>
            </div>
            @error('temp_password_days')
                <p style="margin-top: 8px; font-size: 13px; color: #dc2626;">{{ $message }}</p>
            @enderror
        </div>

        {{-- O'zgartirilgan parol --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #10b981, #34d399);"></div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 24px; height: 24px; color: #059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #111827;">O'zgartirilgan parol</div>
                    <div style="font-size: 12px; color: #6b7280;">Talaba o'zi o'rnatganidan keyin</div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="number" name="changed_password_days" id="changed_password_days" min="1" max="365"
                       value="{{ old('changed_password_days', $changedPasswordDays) }}"
                       style="width: 100px; padding: 10px 14px; border: 2px solid #34d399; border-radius: 10px; font-size: 22px; font-weight: 700; color: #065f46; text-align: center; background: #ecfdf5;">
                <span style="font-size: 16px; color: #065f46; font-weight: 600;">kun</span>
            </div>
            @error('changed_password_days')
                <p style="margin-top: 8px; font-size: 13px; color: #dc2626;">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div style="display: flex; justify-content: flex-end;">
        <button type="submit"
                style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background: #2563eb; color: #ffffff; font-size: 14px; font-weight: 600; border-radius: 8px; border: none; cursor: pointer;"
                onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Saqlash
        </button>
    </div>
</form>
