<form method="POST" action="{{ route('admin.settings.update.deadlines') }}">
    @csrf

    {{-- Summary cards --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
        <div style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 12px; padding: 20px; border: 1px solid #fbbf24;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 40px; height: 40px; background-color: rgba(245,158,11,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 22px; height: 22px; color: #b45309;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <div style="font-size: 12px; color: #92400e; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Spravka muddati</div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="number" name="spravka_deadline_days" value="{{ old('spravka_deadline_days', $spravkaDays ?? 10) }}" min="1"
                       style="width: 80px; padding: 8px 12px; border: 2px solid #d97706; border-radius: 8px; font-size: 20px; font-weight: 700; color: #92400e; text-align: center; background: rgba(255,255,255,0.7);">
                <span style="font-size: 14px; color: #92400e; font-weight: 500;">kun</span>
            </div>
        </div>

        <div style="background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-radius: 12px; padding: 20px; border: 1px solid #60a5fa;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 40px; height: 40px; background-color: rgba(37,99,235,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 22px; height: 22px; color: #1d4ed8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <div style="font-size: 12px; color: #1e40af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">MT muddat vaqti</div>
                </div>
            </div>
            <input type="time" name="mt_deadline_time" value="{{ old('mt_deadline_time', $mtDeadlineTime ?? '17:00') }}"
                   style="width: 120px; padding: 8px 12px; border: 2px solid #3b82f6; border-radius: 8px; font-size: 20px; font-weight: 700; color: #1e40af; text-align: center; background: rgba(255,255,255,0.7);">
        </div>

        <div style="background: linear-gradient(135deg, #ede9fe, #ddd6fe); border-radius: 12px; padding: 20px; border: 1px solid #a78bfa;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 40px; height: 40px; background-color: rgba(124,58,237,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 22px; height: 22px; color: #6d28d9;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </div>
                <div>
                    <div style="font-size: 12px; color: #5b21b6; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Qayta yuklash</div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <input type="number" name="mt_max_resubmissions" value="{{ old('mt_max_resubmissions', $mtMaxResubmissions ?? 3) }}" min="0" max="10"
                       style="width: 80px; padding: 8px 12px; border: 2px solid #7c3aed; border-radius: 8px; font-size: 20px; font-weight: 700; color: #5b21b6; text-align: center; background: rgba(255,255,255,0.7);">
                <span style="font-size: 14px; color: #5b21b6; font-weight: 500;">marta</span>
            </div>
            <div style="font-size: 11px; color: #6d28d9; margin-top: 6px;">baho 60 dan past bo'lsa</div>
        </div>
    </div>

    {{-- MT deadline type --}}
    <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
        <div style="font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
            <svg style="width: 20px; height: 20px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Mustaqil ta'lim topshiriq muddati turi
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            @php $types = [
                'before_last' => ['label' => 'Oxirgi darsdan bitta oldingi darsda', 'color' => '#2563eb'],
                'last' => ['label' => 'Oxirgi darsda', 'color' => '#059669'],
                'fixed_days' => ['label' => 'Dars sanasidan + N kun (muddat kunlari asosida)', 'color' => '#d97706'],
            ]; @endphp
            @foreach($types as $value => $type)
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 8px; border: 2px solid {{ old('mt_deadline_type', $mtDeadlineType) == $value ? $type['color'] : '#e5e7eb' }}; background: {{ old('mt_deadline_type', $mtDeadlineType) == $value ? $type['color'] . '10' : '#ffffff' }}; cursor: pointer; transition: all 0.15s;">
                    <input type="radio" name="mt_deadline_type" value="{{ $value }}"
                           {{ old('mt_deadline_type', $mtDeadlineType) == $value ? 'checked' : '' }}
                           style="width: 18px; height: 18px; accent-color: {{ $type['color'] }};">
                    <span style="font-size: 14px; color: #374151; font-weight: 500;">{{ $type['label'] }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Per-level deadlines --}}
    <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
        <div style="font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <svg style="width: 20px; height: 20px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            Kurs darajalari bo'yicha muddatlar
        </div>

        @foreach ($deadlines as $deadline)
            <div style="padding: 16px; margin-bottom: 12px; background: #f9fafb; border-radius: 10px; border: 1px solid #f3f4f6;">
                <div style="font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb;">
                    {{ $deadline->level->level_name ?? $deadline->level_code }}
                    <span style="font-size: 12px; color: #9ca3af; font-weight: 400; margin-left: 4px;">({{ $deadline->level_code }})</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                    <div>
                        <label style="font-size: 12px; color: #6b7280; font-weight: 500; display: block; margin-bottom: 6px;">Muddat (kunlar)</label>
                        <input type="number" name="deadlines[{{ $deadline->level_code }}][days]"
                               value="{{ old('deadlines.' . $deadline->level_code . '.days', $deadline->deadline_days ?? '') }}"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827;">
                    </div>
                    <div>
                        <label style="font-size: 12px; color: #6b7280; font-weight: 500; display: block; margin-bottom: 6px;">Joriy nazorat bali</label>
                        <input type="number" name="deadlines[{{ $deadline->level_code }}][joriy]"
                               value="{{ old('deadlines.' . $deadline->level_code . '.joriy', $deadline->joriy ?? '') }}"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827;">
                    </div>
                    <div>
                        <label style="font-size: 12px; color: #6b7280; font-weight: 500; display: block; margin-bottom: 6px;">MT o'tish bali</label>
                        <input type="number" name="deadlines[{{ $deadline->level_code }}][mustaqil_talim]"
                               value="{{ old('deadlines.' . $deadline->level_code . '.mustaqil_talim', $deadline->mustaqil_talim ?? '') }}"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827;">
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div style="display: flex; justify-content: flex-end;">
        <button type="submit"
                style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background: #2563eb; color: #ffffff; font-size: 14px; font-weight: 600; border-radius: 8px; border: none; cursor: pointer;"
                onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Muddatlarni saqlash
        </button>
    </div>
</form>
