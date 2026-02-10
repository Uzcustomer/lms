<form method="POST" action="{{ route('admin.settings.update.telegram') }}">
    @csrf

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        {{-- Left: Setting card --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0ea5e9, #38bdf8);"></div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #e0f2fe, #bae6fd); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 26px; height: 26px; color: #0284c7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <div>
                    <div style="font-size: 16px; font-weight: 700; color: #111827;">Aloqa ma'lumotlari tasdiqlash muddati</div>
                    <div style="font-size: 13px; color: #6b7280;">Telefon raqami kiritilganidan keyin (xodim va talaba uchun)</div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <input type="number" name="telegram_deadline_days" id="telegram_deadline_days" min="1" max="365"
                       value="{{ old('telegram_deadline_days', $telegramDeadlineDays) }}"
                       style="width: 110px; padding: 12px 16px; border: 2px solid #38bdf8; border-radius: 12px; font-size: 28px; font-weight: 700; color: #0c4a6e; text-align: center; background: #f0f9ff;">
                <span style="font-size: 18px; color: #0c4a6e; font-weight: 600;">kun</span>
            </div>
            @error('telegram_deadline_days')
                <p style="margin-top: 8px; font-size: 13px; color: #dc2626;">{{ $message }}</p>
            @enderror

            <button type="submit"
                    style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background: #0284c7; color: #ffffff; font-size: 14px; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; margin-top: 16px;"
                    onmouseover="this.style.background='#0369a1'" onmouseout="this.style.background='#0284c7'">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Saqlash
            </button>
        </div>

        {{-- Right: How it works --}}
        <div style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-radius: 12px; padding: 24px; border: 1px solid #7dd3fc;">
            <div style="font-size: 15px; font-weight: 600; color: #0c4a6e; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Qanday ishlaydi?
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="width: 28px; height: 28px; background: #0284c7; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0;">1</div>
                    <div style="font-size: 13px; color: #0c4a6e; line-height: 1.5; padding-top: 4px;">Foydalanuvchi (xodim/talaba) telefon raqamini kiritadi</div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="width: 28px; height: 28px; background: #0284c7; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0;">2</div>
                    <div style="font-size: 13px; color: #0c4a6e; line-height: 1.5; padding-top: 4px;">Belgilangan <b>{{ $telegramDeadlineDays }} kun</b> ichida Telegram username tasdiqlanishi kerak</div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="width: 28px; height: 28px; background: #0284c7; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0;">3</div>
                    <div style="font-size: 13px; color: #0c4a6e; line-height: 1.5; padding-top: 4px;">Ogohlantirish: qizil (2 kun), sariq (4 kun), ko'k (5+ kun)</div>
                </div>
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="width: 28px; height: 28px; background: #dc2626; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0;">!</div>
                    <div style="font-size: 13px; color: #991b1b; line-height: 1.5; padding-top: 4px; font-weight: 600;">Muddat o'tgandan keyin akkaunt bloklanadi</div>
                </div>
            </div>
        </div>
    </div>
</form>
