<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Guruh ma'lumotlarim") }}
        </h2>
    </x-slot>

    {{--
        Uslublar shu yerda, oddiy CSS'da: serverda `npm run build` qilinmaydi,
        loyihada avval ishlatilmagan Tailwind klasslari yig'ilgan CSS'da bo'lmaydi.
    --}}
    <style>
        .gi-wrap { max-width: 640px; margin: 0 auto; padding: 16px 12px 28px; display: grid; gap: 14px; }

        .gi-hero {
            position: relative; overflow: hidden; border-radius: 22px; padding: 22px 20px 20px;
            background: linear-gradient(135deg, #0f2748 0%, #134e5e 55%, #0f766e 100%);
            color: #fff; box-shadow: 0 12px 30px rgba(15, 39, 72, .22);
        }
        .gi-hero::after {
            content: ""; position: absolute; right: -40px; top: -40px; width: 170px; height: 170px;
            border-radius: 50%; background: rgba(255, 255, 255, .07);
        }
        .gi-hero-label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.65); }
        .gi-hero-name { margin-top: 6px; font-size: 30px; font-weight: 800; line-height: 1.1; letter-spacing: -.01em; }
        .gi-chips { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 6px; position: relative; z-index: 1; }
        .gi-chip {
            display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 999px;
            background: rgba(255, 255, 255, .14); color: #fff; font-size: 12px; font-weight: 600;
        }
        .gi-chip-new { background: #34d399; color: #064e3b; }

        .gi-card { background: #fff; border: 1px solid #e5eaf2; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 39, 72, .05); }
        .gi-card-head { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #eef2f7; }
        .gi-card-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .gi-card-title { font-size: 15px; font-weight: 700; color: #17233a; }

        .gi-move { display: flex; align-items: stretch; gap: 10px; padding: 16px 18px 4px; }
        .gi-move-box { flex: 1; min-width: 0; padding: 12px 10px; border-radius: 14px; text-align: center; }
        .gi-move-box small { display: block; font-size: 11px; font-weight: 700; letter-spacing: .03em; }
        .gi-move-box b { display: block; margin-top: 4px; font-size: 15px; font-weight: 800; word-break: break-word; }
        .gi-move-old { background: #f4f6fa; border: 1px solid #e5eaf2; }
        .gi-move-old small { color: #94a3b8; }
        .gi-move-old b { color: #64748b; text-decoration: line-through; text-decoration-color: #cbd5e1; }
        .gi-move-new { background: #ecfdf5; border: 1px solid #a7f3d0; }
        .gi-move-new small { color: #059669; }
        .gi-move-new b { color: #065f46; }
        .gi-move-arrow { display: flex; align-items: center; color: #10b981; flex-shrink: 0; }
        .gi-note {
            display: flex; gap: 10px; margin: 12px 18px 18px; padding: 11px 13px; border-radius: 12px;
            background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 13px; line-height: 1.5;
        }

        .gi-tutor { padding: 16px 18px; }
        .gi-tutor + .gi-tutor { border-top: 1px solid #eef2f7; }
        .gi-tutor-top { display: flex; align-items: center; gap: 13px; }
        .gi-avatar {
            width: 54px; height: 54px; border-radius: 16px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #14b8a6, #0f766e); color: #fff;
            font-size: 18px; font-weight: 800; letter-spacing: .02em;
        }
        .gi-tutor-name { font-size: 16px; font-weight: 700; color: #17233a; line-height: 1.25; }
        .gi-tutor-role { margin-top: 3px; font-size: 12px; color: #64748b; }
        .gi-phone {
            display: flex; align-items: center; gap: 8px; margin-top: 14px; padding: 11px 13px;
            border-radius: 12px; background: #f7f9fc; border: 1px solid #eef2f7;
            font-size: 15px; font-weight: 700; color: #17233a; letter-spacing: .01em;
        }
        .gi-phone-empty { font-size: 13px; font-weight: 500; color: #94a3b8; }
        .gi-actions { display: flex; gap: 8px; margin-top: 10px; }
        .gi-btn {
            flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            height: 44px; border-radius: 12px; font-size: 14px; font-weight: 700; text-decoration: none;
            transition: filter .15s, transform .15s;
        }
        .gi-btn:active { transform: scale(.98); }
        .gi-btn:hover { filter: brightness(1.06); }
        .gi-btn-call { background: #059669; color: #fff; }
        .gi-btn-tg { background: #0ea5e9; color: #fff; }

        .gi-empty { padding: 30px 20px 32px; text-align: center; }
        .gi-empty-icon {
            width: 60px; height: 60px; margin: 0 auto 12px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #94a3b8;
        }
        .gi-empty b { display: block; font-size: 15px; color: #334155; }
        .gi-empty p { margin: 6px auto 0; max-width: 300px; font-size: 13px; line-height: 1.55; color: #64748b; }
    </style>

    <div class="gi-wrap">

        {{-- Guruh --}}
        <div class="gi-hero">
            <div class="gi-hero-label">{{ __('Guruhingiz') }}</div>
            <div class="gi-hero-name">{{ $groupName ?: '—' }}</div>
            <div class="gi-chips">
                @if($draft)
                    <span class="gi-chip gi-chip-new">{{ __('Yangi guruh') }}</span>
                @endif
                @if($student->specialty_name)
                    <span class="gi-chip">{{ $student->specialty_name }}</span>
                @endif
                @if($student->level_name)
                    <span class="gi-chip">{{ $student->level_name }}</span>
                @endif
            </div>
        </div>

        {{-- Taqsimotda o'tkazilgan talaba: eski va yangi guruh --}}
        @if($draft)
            <div class="gi-card">
                <div class="gi-move">
                    <div class="gi-move-box gi-move-old">
                        <small>{{ __('Avvalgi') }}</small>
                        <b>{{ $draft->from_group_name ?: '—' }}</b>
                    </div>
                    <div class="gi-move-arrow">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </div>
                    <div class="gi-move-box gi-move-new">
                        <small>{{ __('Yangi') }}</small>
                        <b>{{ $draft->to_group_name }}</b>
                    </div>
                </div>
                <div class="gi-note">
                    <svg width="18" height="18" style="flex-shrink:0;margin-top:1px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <span>{{ __("Siz :group guruhiga o'tkazildingiz. Ma'lumot tez orada yangilanadi.", ['group' => $draft->to_group_name]) }}</span>
                </div>
            </div>
        @endif

        {{-- Tyutor --}}
        <div class="gi-card">
            <div class="gi-card-head">
                <div class="gi-card-icon" style="background:#ccfbf1;color:#0d9488;">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </div>
                <div class="gi-card-title">{{ __('Guruh tyutori') }}</div>
            </div>

            @forelse($tutors as $tutor)
                @php
                    $nameParts = preg_split('/\s+/u', trim((string) $tutor->full_name)) ?: [];
                    $initials = mb_strtoupper(collect($nameParts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
                    $phone = trim((string) $tutor->phone);
                    $phoneDigits = preg_replace('/\D+/', '', $phone);
                    // Mahalliy 9 xonali raqamga +998 qo'shiladi
                    $telHref = $phoneDigits !== '' ? '+' . (strlen($phoneDigits) === 9 ? '998' . $phoneDigits : $phoneDigits) : null;
                    $tg = ltrim(trim((string) $tutor->telegram_username), '@');
                @endphp
                <div class="gi-tutor">
                    <div class="gi-tutor-top">
                        <div class="gi-avatar">{{ $initials ?: '?' }}</div>
                        <div style="min-width:0;">
                            <div class="gi-tutor-name">{{ $tutor->full_name }}</div>
                            <div class="gi-tutor-role">{{ __('Tyutor') }}</div>
                        </div>
                    </div>

                    <div class="gi-phone">
                        <svg width="17" height="17" style="color:#0d9488;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                        </svg>
                        @if($phone !== '')
                            <span>{{ $phone }}</span>
                        @else
                            <span class="gi-phone-empty">{{ __('Telefon raqami kiritilmagan') }}</span>
                        @endif
                    </div>

                    @if($telHref || $tg !== '')
                        <div class="gi-actions">
                            @if($telHref)
                                <a href="tel:{{ $telHref }}" class="gi-btn gi-btn-call">
                                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                    </svg>
                                    {{ __("Qo'ng'iroq qilish") }}
                                </a>
                            @endif
                            @if($tg !== '')
                                <a href="https://t.me/{{ $tg }}" target="_blank" rel="noopener noreferrer" class="gi-btn gi-btn-tg">
                                    <svg width="17" height="17" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
                                    </svg>
                                    Telegram
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="gi-empty">
                    <div class="gi-empty-icon">
                        <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <b>{{ __('Tyutor biriktirilmagan') }}</b>
                    <p>{{ __("Guruhingizga hali tyutor biriktirilmagan. Savol bo'lsa dekanatga murojaat qiling.") }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-student-app-layout>
