<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Sozlamalar
        </h2>
    </x-slot>

    <style>
        #stg svg { flex-shrink: 0 !important; display: inline-block !important; }
    </style>

    <div class="py-4" id="stg">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            @if (session('success'))
                <div style="padding: 14px 20px; margin-bottom: 20px; font-size: 14px; color: #065f46; background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-radius: 12px; border: 1px solid #6ee7b7; display: flex; align-items: center; gap: 10px;">
                    <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div style="padding: 14px 20px; margin-bottom: 20px; font-size: 14px; color: #991b1b; background: linear-gradient(135deg, #fef2f2, #fecaca); border-radius: 12px; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 10px;">
                    <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            {{-- SECTION 1: MUDDATLAR --}}
            <div x-data="{ open: true }" style="background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; margin-bottom: 20px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                <button @click="open = !open" type="button" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; background: linear-gradient(135deg, #eff6ff, #dbeafe); border: none; cursor: pointer; border-bottom: 1px solid #bfdbfe;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 44px; height: 44px; min-width: 44px; background: linear-gradient(135deg, #2563eb, #3b82f6); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(37,99,235,0.3);">
                            <svg width="22" height="22" style="width: 22px; height: 22px; color: #fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-size: 17px; font-weight: 700; color: #1e3a5f;">Muddatlar va ballar</div>
                            <div style="font-size: 13px; color: #64748b; margin-top: 2px;">Spravka, MT muddatlari, kurs darajasi sozlamalari</div>
                        </div>
                    </div>
                    <svg width="20" height="20" :style="open ? 'transform: rotate(180deg)' : ''" style="width: 20px; height: 20px; min-width: 20px; color: #64748b; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" x-transition style="padding: 24px;">
                    <form method="POST" action="{{ route('admin.settings.update.deadlines') }}">
                        @csrf

                        {{-- Quick settings row --}}
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
                            {{-- Spravka --}}
                            <div style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 14px; padding: 20px; border: 1px solid #fbbf24; position: relative; overflow: hidden;">
                                <div style="position: absolute; top: 0; right: 0; width: 80px; height: 80px; background: rgba(245,158,11,0.1); border-radius: 0 0 0 80px;"></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                                    <div style="width: 36px; height: 36px; min-width: 36px; background: rgba(245,158,11,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" style="width: 20px; height: 20px; color: #b45309;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div style="font-size: 13px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">Spravka muddati</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" name="spravka_deadline_days" value="{{ old('spravka_deadline_days', $spravkaDays ?? 10) }}" min="1" style="width: 80px; padding: 10px 14px; border: 2px solid #d97706; border-radius: 10px; font-size: 24px; font-weight: 800; color: #92400e; text-align: center; background: rgba(255,255,255,0.7); outline: none;">
                                    <span style="font-size: 15px; color: #92400e; font-weight: 600;">kun</span>
                                </div>
                            </div>

                            {{-- MT vaqt --}}
                            <div style="background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-radius: 14px; padding: 20px; border: 1px solid #60a5fa; position: relative; overflow: hidden;">
                                <div style="position: absolute; top: 0; right: 0; width: 80px; height: 80px; background: rgba(37,99,235,0.08); border-radius: 0 0 0 80px;"></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                                    <div style="width: 36px; height: 36px; min-width: 36px; background: rgba(37,99,235,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" style="width: 20px; height: 20px; color: #1d4ed8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div style="font-size: 13px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">MT muddat vaqti</div>
                                </div>
                                <input type="time" name="mt_deadline_time" value="{{ old('mt_deadline_time', $mtDeadlineTime ?? '17:00') }}" style="width: 120px; padding: 10px 14px; border: 2px solid #3b82f6; border-radius: 10px; font-size: 24px; font-weight: 800; color: #1e40af; text-align: center; background: rgba(255,255,255,0.7); outline: none;">
                            </div>

                            {{-- Qayta yuklash --}}
                            <div style="background: linear-gradient(135deg, #ede9fe, #ddd6fe); border-radius: 14px; padding: 20px; border: 1px solid #a78bfa; position: relative; overflow: hidden;">
                                <div style="position: absolute; top: 0; right: 0; width: 80px; height: 80px; background: rgba(124,58,237,0.08); border-radius: 0 0 0 80px;"></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                                    <div style="width: 36px; height: 36px; min-width: 36px; background: rgba(124,58,237,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" style="width: 20px; height: 20px; color: #6d28d9;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </div>
                                    <div style="font-size: 13px; font-weight: 700; color: #5b21b6; text-transform: uppercase; letter-spacing: 0.5px;">Qayta yuklash</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" name="mt_max_resubmissions" value="{{ old('mt_max_resubmissions', $mtMaxResubmissions ?? 3) }}" min="0" max="10" style="width: 80px; padding: 10px 14px; border: 2px solid #7c3aed; border-radius: 10px; font-size: 24px; font-weight: 800; color: #5b21b6; text-align: center; background: rgba(255,255,255,0.7); outline: none;">
                                    <span style="font-size: 15px; color: #5b21b6; font-weight: 600;">marta</span>
                                </div>
                                <div style="font-size: 11px; color: #7c3aed; margin-top: 6px;">baho 60 dan past bo'lsa</div>
                            </div>

                            {{-- Dars ochish muddati --}}
                            <div style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-radius: 14px; padding: 20px; border: 1px solid #4ade80; position: relative; overflow: hidden;">
                                <div style="position: absolute; top: 0; right: 0; width: 80px; height: 80px; background: rgba(34,197,94,0.1); border-radius: 0 0 0 80px;"></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                                    <div style="width: 36px; height: 36px; min-width: 36px; background: rgba(34,197,94,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" style="width: 20px; height: 20px; color: #15803d;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    </div>
                                    <div style="font-size: 13px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">Dars ochish</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" name="lesson_opening_days" value="{{ old('lesson_opening_days', $lessonOpeningDays ?? 3) }}" min="1" max="30" style="width: 80px; padding: 10px 14px; border: 2px solid #22c55e; border-radius: 10px; font-size: 24px; font-weight: 800; color: #166534; text-align: center; background: rgba(255,255,255,0.7); outline: none;">
                                    <span style="font-size: 15px; color: #166534; font-weight: 600;">kun</span>
                                </div>
                                <div style="font-size: 11px; color: #22c55e; margin-top: 6px;">baho qo'yish muddati</div>
                            </div>
                        </div>

                        {{-- MT deadline type --}}
                        <div style="background: #f8fafc; border-radius: 14px; padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
                            <div style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                                <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px; color: #64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Mustaqil ta'lim topshiriq muddati turi
                            </div>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                @php $types = [
                                    'before_last' => ['label' => 'Oxirgi darsdan oldingi dars', 'color' => '#2563eb', 'bg' => '#eff6ff'],
                                    'last' => ['label' => 'Oxirgi dars', 'color' => '#059669', 'bg' => '#ecfdf5'],
                                    'fixed_days' => ['label' => 'Dars + N kun', 'color' => '#d97706', 'bg' => '#fffbeb'],
                                ]; @endphp
                                @foreach($types as $value => $type)
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-radius: 10px; border: 2px solid {{ old('mt_deadline_type', $mtDeadlineType) == $value ? $type['color'] : '#e2e8f0' }}; background: {{ old('mt_deadline_type', $mtDeadlineType) == $value ? $type['bg'] : '#fff' }}; cursor: pointer; flex: 1; min-width: 180px;">
                                        <input type="radio" name="mt_deadline_type" value="{{ $value }}" {{ old('mt_deadline_type', $mtDeadlineType) == $value ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: {{ $type['color'] }};">
                                        <span style="font-size: 13px; color: #374151; font-weight: 500;">{{ $type['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Per-level deadlines --}}
                        <div style="background: #f8fafc; border-radius: 14px; padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
                            <div style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px; color: #64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                Kurs darajalari bo'yicha muddatlar
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px;">
                                @foreach ($deadlines as $deadline)
                                    <div style="padding: 14px 16px; background: #fff; border-radius: 10px; border: 1px solid #e5e7eb;">
                                        <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                                            {{ $deadline->level->level_name ?? $deadline->level_code }}
                                            <span style="font-size: 11px; color: #94a3b8; font-weight: 400; margin-left: 4px;">({{ $deadline->level_code }})</span>
                                        </div>
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                            <div>
                                                <label style="font-size: 11px; color: #64748b; font-weight: 500; display: block; margin-bottom: 4px;">Kunlar</label>
                                                <input type="number" name="deadlines[{{ $deadline->level_code }}][days]" value="{{ old('deadlines.' . $deadline->level_code . '.days', $deadline->deadline_days ?? '') }}" style="width: 100%; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827; outline: none;">
                                            </div>
                                            <div>
                                                <label style="font-size: 11px; color: #64748b; font-weight: 500; display: block; margin-bottom: 4px;">JN bal</label>
                                                <input type="number" name="deadlines[{{ $deadline->level_code }}][joriy]" value="{{ old('deadlines.' . $deadline->level_code . '.joriy', $deadline->joriy ?? '') }}" style="width: 100%; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827; outline: none;">
                                            </div>
                                            <div>
                                                <label style="font-size: 11px; color: #64748b; font-weight: 500; display: block; margin-bottom: 4px;">MT bal</label>
                                                <input type="number" name="deadlines[{{ $deadline->level_code }}][mustaqil_talim]" value="{{ old('deadlines.' . $deadline->level_code . '.mustaqil_talim', $deadline->mustaqil_talim ?? '') }}" style="width: 100%; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827; outline: none;">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 28px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-size: 14px; font-weight: 600; border-radius: 10px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,0.3);">
                                <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Muddatlarni saqlash
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- SECTION 2: PAROL va TELEGRAM --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">

                {{-- Password --}}
                <div x-data="{ open: true }" style="background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <button @click="open = !open" type="button" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; background: linear-gradient(135deg, #fef3c7, #fde68a); border: none; cursor: pointer; border-bottom: 1px solid #fbbf24;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; min-width: 40px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(245,158,11,0.3);">
                                <svg width="20" height="20" style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <div style="text-align: left;">
                                <div style="font-size: 16px; font-weight: 700; color: #78350f;">Parol sozlamalari</div>
                                <div style="font-size: 12px; color: #92400e;">Vaqtinchalik va doimiy parol muddatlari</div>
                            </div>
                        </div>
                        <svg width="18" height="18" :style="open ? 'transform: rotate(180deg)' : ''" style="width: 18px; height: 18px; min-width: 18px; color: #92400e; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-transition style="padding: 22px;">
                        <form method="POST" action="{{ route('admin.settings.update.password') }}">
                            @csrf
                            <div style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 10px; padding: 14px; margin-bottom: 18px; border: 1px solid #93c5fd; font-size: 12px; color: #1e40af; line-height: 1.7;">
                                <b>Jarayon:</b> Admin parolni tiklaydi → Talaba vaqtinchalik parol bilan kiradi → Yangi parol o'rnatadi → Bu parol ham muddatli
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #fffbeb; border-radius: 10px; border: 1px solid #fde68a; margin-bottom: 12px;">
                                <div>
                                    <div style="font-size: 14px; font-weight: 600; color: #92400e;">Vaqtinchalik parol</div>
                                    <div style="font-size: 12px; color: #b45309;">Admin tiklaganidan keyin</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" name="temp_password_days" min="1" max="365" value="{{ old('temp_password_days', $tempPasswordDays) }}" style="width: 70px; padding: 8px 10px; border: 2px solid #fbbf24; border-radius: 8px; font-size: 20px; font-weight: 800; color: #92400e; text-align: center; background: #fff; outline: none;">
                                    <span style="font-size: 14px; color: #92400e; font-weight: 600;">kun</span>
                                </div>
                            </div>
                            @error('temp_password_days') <p style="font-size: 12px; color: #dc2626; margin: -8px 0 12px 0;">{{ $message }}</p> @enderror

                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #ecfdf5; border-radius: 10px; border: 1px solid #a7f3d0; margin-bottom: 16px;">
                                <div>
                                    <div style="font-size: 14px; font-weight: 600; color: #065f46;">O'zgartirilgan parol</div>
                                    <div style="font-size: 12px; color: #047857;">Talaba o'zi o'rnatganidan keyin</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" name="changed_password_days" min="1" max="365" value="{{ old('changed_password_days', $changedPasswordDays) }}" style="width: 70px; padding: 8px 10px; border: 2px solid #34d399; border-radius: 8px; font-size: 20px; font-weight: 800; color: #065f46; text-align: center; background: #fff; outline: none;">
                                    <span style="font-size: 14px; color: #065f46; font-weight: 600;">kun</span>
                                </div>
                            </div>
                            @error('changed_password_days') <p style="font-size: 12px; color: #dc2626; margin: -12px 0 12px 0;">{{ $message }}</p> @enderror

                            <button type="submit" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-size: 14px; font-weight: 600; border-radius: 10px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(245,158,11,0.3);">
                                <svg width="16" height="16" style="width: 16px; height: 16px; min-width: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Saqlash
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Telegram --}}
                <div x-data="{ open: true }" style="background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <button @click="open = !open" type="button" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; background: linear-gradient(135deg, #e0f2fe, #bae6fd); border: none; cursor: pointer; border-bottom: 1px solid #7dd3fc;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; min-width: 40px; background: linear-gradient(135deg, #0ea5e9, #0284c7); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(14,165,233,0.3);">
                                <svg width="20" height="20" style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <div style="text-align: left;">
                                <div style="font-size: 16px; font-weight: 700; color: #0c4a6e;">Aloqa ma'lumotlari sozlamalari</div>
                                <div style="font-size: 12px; color: #0369a1;">Telefon va Telegram tasdiqlash muddati (xodim va talaba uchun)</div>
                            </div>
                        </div>
                        <svg width="18" height="18" :style="open ? 'transform: rotate(180deg)' : ''" style="width: 18px; height: 18px; min-width: 18px; color: #0369a1; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-transition style="padding: 22px;">
                        <form method="POST" action="{{ route('admin.settings.update.telegram') }}">
                            @csrf
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; background: #f0f9ff; border-radius: 10px; border: 1px solid #bae6fd; margin-bottom: 18px;">
                                <div>
                                    <div style="font-size: 14px; font-weight: 600; color: #0c4a6e;">Tasdiqlash muddati</div>
                                    <div style="font-size: 12px; color: #0369a1;">Telefon raqami kiritilgandan keyin</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" name="telegram_deadline_days" min="1" max="365" value="{{ old('telegram_deadline_days', $telegramDeadlineDays) }}" style="width: 70px; padding: 8px 10px; border: 2px solid #38bdf8; border-radius: 8px; font-size: 20px; font-weight: 800; color: #0c4a6e; text-align: center; background: #fff; outline: none;">
                                    <span style="font-size: 14px; color: #0c4a6e; font-weight: 600;">kun</span>
                                </div>
                            </div>
                            @error('telegram_deadline_days') <p style="font-size: 12px; color: #dc2626; margin: -14px 0 14px 0;">{{ $message }}</p> @enderror

                            <div style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-radius: 10px; padding: 16px; border: 1px solid #bae6fd; margin-bottom: 18px;">
                                <div style="font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 12px;">Qanday ishlaydi?</div>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span style="width: 22px; height: 22px; min-width: 22px; background: #0284c7; color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">1</span>
                                        <span style="font-size: 12px; color: #0c4a6e;">Foydalanuvchi (xodim/talaba) telefon raqamini kiritadi</span>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span style="width: 22px; height: 22px; min-width: 22px; background: #0284c7; color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">2</span>
                                        <span style="font-size: 12px; color: #0c4a6e;"><b>{{ $telegramDeadlineDays }} kun</b> ichida username tasdiqlanishi kerak</span>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span style="width: 22px; height: 22px; min-width: 22px; background: #0284c7; color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">3</span>
                                        <span style="font-size: 12px; color: #0c4a6e;">Ogohlantirish: <span style="color: #dc2626; font-weight: 600;">qizil</span> (2 kun), <span style="color: #d97706; font-weight: 600;">sariq</span> (4 kun), <span style="color: #2563eb; font-weight: 600;">ko'k</span> (5+)</span>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span style="width: 22px; height: 22px; min-width: 22px; background: #dc2626; color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">!</span>
                                        <span style="font-size: 12px; color: #991b1b; font-weight: 600;">Muddat o'tsa akkaunt bloklanadi</span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; background: linear-gradient(135deg, #0ea5e9, #0284c7); color: #fff; font-size: 14px; font-weight: 600; border-radius: 10px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(14,165,233,0.3);">
                                <svg width="16" height="16" style="width: 16px; height: 16px; min-width: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Saqlash
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- SECTION 3: SINXRONIZATSIYA --}}
            <div x-data="{ open: true }" style="background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; margin-bottom: 20px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                <button @click="open = !open" type="button" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: none; cursor: pointer; border-bottom: 1px solid #6ee7b7;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 44px; height: 44px; min-width: 44px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                            <svg width="22" height="22" style="width: 22px; height: 22px; color: #fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-size: 17px; font-weight: 700; color: #064e3b;">Sinxronizatsiya</div>
                            <div style="font-size: 13px; color: #047857; margin-top: 2px;">HEMIS API dan ma'lumotlarni yangilash</div>
                        </div>
                    </div>
                    <svg width="20" height="20" :style="open ? 'transform: rotate(180deg)' : ''" style="width: 20px; height: 20px; min-width: 20px; color: #047857; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" x-transition style="padding: 24px;">
                    {{-- Schedule sync --}}
                    <div style="background: #f8fafc; border-radius: 14px; padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 4px;">Dars jadvalini sinxronlash</div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 14px;">Tanlangan vaqt oralig'i uchun jadvallarni HEMIS dan yangilash</div>

                        <div style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; border: 1px solid #93c5fd;">
                            <div style="font-size: 12px; color: #1e40af; line-height: 1.6;">
                                <svg width="14" height="14" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Fon rejimida ishlaydi. Tizim yuklamasi kam bo'lgan vaqtda ishlatish tavsiya etiladi.
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.synchronize') }}">
                            @csrf
                            <div style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap;">
                                <div>
                                    <label style="font-size: 12px; color: #64748b; font-weight: 500; display: block; margin-bottom: 4px;">Boshlanish sanasi</label>
                                    <input type="date" name="start_date" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827; outline: none;">
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: #64748b; font-weight: 500; display: block; margin-bottom: 4px;">Tugash sanasi</label>
                                    <input type="date" name="end_date" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827; outline: none;">
                                </div>
                                <button type="submit" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-size: 13px; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; box-shadow: 0 2px 8px rgba(37,99,235,0.25);">
                                    <svg width="16" height="16" style="width: 16px; height: 16px; min-width: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Jadvallarni yangilash
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- HEMIS sync --}}
                    <div style="background: #f8fafc; border-radius: 14px; padding: 20px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 4px;">HEMIS ma'lumotlarini sinxronlash</div>
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 14px;">HEMIS API dan ma'lumotlarni yuklab, mahalliy bazaga saqlash</div>

                        <div style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; border: 1px solid #fde68a;">
                            <div style="font-size: 12px; color: #92400e; line-height: 1.6;">
                                <svg width="14" height="14" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Har bir jarayon fon rejimida ishlaydi va bir necha daqiqa davom etishi mumkin.
                            </div>
                        </div>

                        @php
                            $syncButtons = [
                                ['route' => 'admin.synchronize.curricula', 'label' => "O'quv rejalar", 'color' => '#4f46e5', 'hover' => '#4338ca', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                                ['route' => 'admin.synchronize.curriculum-subjects', 'label' => "O'quv reja fanlari", 'color' => '#9333ea', 'hover' => '#7e22ce', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                                ['route' => 'admin.synchronize.groups', 'label' => 'Guruhlar', 'color' => '#16a34a', 'hover' => '#15803d', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                                ['route' => 'admin.synchronize.semesters', 'label' => 'Semestrlar', 'color' => '#0d9488', 'hover' => '#0f766e', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                                ['route' => 'admin.synchronize.specialties-departments', 'label' => 'Mutaxassislik/Kafedralar', 'color' => '#ea580c', 'hover' => '#c2410c', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                                ['route' => 'admin.synchronize.students', 'label' => 'Talabalar', 'color' => '#0891b2', 'hover' => '#0e7490', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                                ['route' => 'admin.synchronize.teachers', 'label' => "O'qituvchilar", 'color' => '#e11d48', 'hover' => '#be123c', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                                ['route' => 'admin.synchronize.attendance-controls', 'label' => 'Davomat nazorati', 'color' => '#7c3aed', 'hover' => '#6d28d9', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                            ];
                        @endphp

                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                            @foreach($syncButtons as $btn)
                                <form method="POST" action="{{ route($btn['route']) }}">
                                    @csrf
                                    <button type="submit" style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 14px; background: {{ $btn['color'] }}; color: #fff; font-size: 13px; font-weight: 600; border-radius: 10px; border: none; cursor: pointer; box-shadow: 0 2px 8px {{ $btn['color'] }}40;" onmouseover="this.style.background='{{ $btn['hover'] }}'" onmouseout="this.style.background='{{ $btn['color'] }}'">
                                        <svg width="16" height="16" style="width: 16px; height: 16px; min-width: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $btn['icon'] }}"/></svg>
                                        {{ $btn['label'] }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
