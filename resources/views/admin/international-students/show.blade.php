<x-app-layout>
    <x-slot name="header">
        <div class="sp-topbar">
            <nav class="sp-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('admin.international-students.index') }}" class="sp-crumb-link">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Xalqaro talabalar
                </a>
                <span class="sp-crumb-sep">/</span>
                <span class="sp-crumb-current">{{ $student->full_name }}</span>
            </nav>
            <form method="POST" action="{{ route('admin.international-students.sync-hemis-student', $student) }}">
                @csrf
                <button type="submit" class="sp-btn sp-btn-ghost" title="Davlat, fuqarolik, guruh va holatni HEMIS dan qayta oladi">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    HEMIS dan yangilash
                </button>
            </form>
        </div>
    </x-slot>

    @php
        $flagUrl = \App\Support\CountryFlag::url($student->country_name, $student->country_code);
        $birthFlagUrl = $visaInfo ? \App\Support\CountryFlag::url($visaInfo->birth_country) : null;

        // Muddat holati: qolgan kun va sozlamadagi chegaralar bo'yicha
        $deadline = function (?\Carbon\Carbon $date, ?int $days, array $t) {
            if (!$date) {
                return ['tone' => 'none', 'text' => 'Kiritilmagan'];
            }
            if ($days < 0) {
                return ['tone' => 'bad', 'text' => abs($days) . ' kun oldin tugagan'];
            }
            if ($days === 0) {
                return ['tone' => 'bad', 'text' => 'Bugun tugaydi'];
            }
            $tone = $days <= $t['danger'] ? 'bad' : ($days <= $t['warning'] ? 'warn' : ($days <= $t['info'] ? 'info' : 'ok'));
            return ['tone' => $tone, 'text' => $days . ' kun qoldi'];
        };
        $fmt = fn ($d) => $d?->format('d.m.Y') ?? '—';
    @endphp

    <div class="sp-page">
        @if(session('success'))
            <div class="sp-flash sp-flash-ok" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="sp-flash sp-flash-bad" role="alert">{{ session('error') }}</div>
        @endif

        {{-- Talaba --}}
        <header class="sp-identity">
            <div class="sp-identity-main">
                @if($flagUrl)
                    <img src="{{ $flagUrl }}" alt="{{ $student->country_name }} bayrog'i" class="sp-flag-lg" width="36" height="27">
                @else
                    <span class="sp-flag-lg sp-flag-empty" aria-hidden="true">{{ mb_substr($student->full_name, 0, 1) }}</span>
                @endif
                <div style="min-width:0;">
                    <h1 class="sp-title">{{ $student->full_name }}</h1>
                    <div class="sp-meta">
                        <span><b>{{ $student->country_name ?: 'Davlat noma\'lum' }}</b></span>
                        <span class="sp-dot">·</span>
                        <span>{{ $student->group_name ?: '—' }}</span>
                        @if($student->level_name)<span class="sp-dot">·</span><span>{{ $student->level_name }}</span>@endif
                        @if($student->department_name)<span class="sp-dot">·</span><span>{{ $student->department_name }}</span>@endif
                    </div>
                    <div class="sp-meta sp-meta-sub">
                        @if($student->student_id_number)<span>ID <span class="sp-num">{{ $student->student_id_number }}</span></span>@endif
                        @if($student->phone)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $student->phone) }}" class="sp-link-quiet">{{ $student->phone }}</a>
                        @endif
                        @if($student->telegram_username)
                            <a href="https://t.me/{{ ltrim($student->telegram_username, '@') }}" target="_blank" rel="noopener" class="sp-link-quiet">{{ '@' . ltrim($student->telegram_username, '@') }}</a>
                        @endif
                        @if($student->student_status_code == '60')
                            <span class="sp-pill sp-pill-bad">HEMIS da yo'q</span>
                        @endif
                    </div>
                </div>
            </div>
            @if($visaInfo)
                <div class="sp-identity-side">
                    <div class="sp-pills">
                        @if($visaInfo->status === 'approved')
                            <span class="sp-pill sp-pill-ok">Tasdiqlangan</span>
                        @elseif($visaInfo->status === 'rejected')
                            <span class="sp-pill sp-pill-bad">Rad etilgan</span>
                        @else
                            <span class="sp-pill sp-pill-warn">Tekshirilmoqda</span>
                        @endif
                        <span class="sp-pill">{{ $visaInfo->basisLabel() }}</span>
                    </div>
                    @if($visaInfo->status !== 'pending' && $visaInfo->reviewed_at)
                        <div class="sp-faint">
                            {{ $visaInfo->status === 'approved' ? 'Tasdiqladi' : 'Rad etdi' }}:
                            {{ $visaInfo->reviewer?->name ?? '—' }}, {{ $visaInfo->reviewed_at->format('d.m.Y H:i') }}
                        </div>
                    @endif
                </div>
            @endif
        </header>

        @if(!$visaInfo)
            <section class="sp-card">
                <div class="sp-empty">
                    <div class="sp-empty-title">Talaba hali viza ma'lumotlarini kiritmagan</div>
                    <div class="sp-faint">Ma'lumotlarni quyida o'zingiz to'ldirishingiz mumkin.</div>
                </div>
                <div class="sp-card-body" style="border-top:1px solid var(--sp-line);">
                    @include('admin.international-students._visa-form', ['student' => $student, 'visaInfo' => null])
                </div>
            </section>
        @else
            @php
                $rps = $visaInfo->registration_process_status ?? 'none';
                $vps = $visaInfo->visa_process_status ?? 'none';
                $regT = \App\Services\VisaSettings::thresholds('registration');
                $visaT = \App\Services\VisaSettings::thresholds('visa');
                $permitT = \App\Services\VisaSettings::thresholds('residence_permit');

            @endphp

            {{-- Amallar: tekshiruv, jarayon, firma — bir qatorda --}}
            <div class="sp-actions-row">
                    @if($visaInfo->status === 'pending')
                        <section class="sp-card sp-card-accent" x-data="{ reject: false }">
                            <div class="sp-card-head"><h2 class="sp-h2">Tekshiruv</h2><span class="sp-faint">Ma'lumotlar tekshirilishini kutyapti</span></div>
                            <div class="sp-card-body">
                                @if($visaInfo->rejection_reason)
                                    <div class="sp-note sp-note-bad">Oldingi rad etish sababi: {{ $visaInfo->rejection_reason }}</div>
                                @endif
                                <div class="sp-actions">
                                    <form method="POST" action="{{ route('admin.international-students.approve', $student) }}" style="flex:1;">
                                        @csrf
                                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%;" onclick="return confirm('Ma\'lumotlar tasdiqlansinmi?')">Tasdiqlash</button>
                                    </form>
                                    <button type="button" class="sp-btn sp-btn-ghost-bad" @click="reject = !reject" :aria-expanded="reject">Rad etish</button>
                                </div>
                                <form x-show="reject" x-cloak method="POST" action="{{ route('admin.international-students.reject', $student) }}" style="margin-top:10px;">
                                    @csrf
                                    <label for="rejection_reason" class="sp-label">Rad etish sababi</label>
                                    <textarea id="rejection_reason" name="rejection_reason" rows="2" required class="sp-input" placeholder="Talabaga ko'rsatiladi"></textarea>
                                    <button type="submit" class="sp-btn sp-btn-bad" style="margin-top:8px;">Rad etishni yuborish</button>
                                </form>
                            </div>
                        </section>
                    @endif

                    <section class="sp-card">
                        <div class="sp-card-head"><h2 class="sp-h2">Jarayon</h2></div>
                        <ul class="sp-list">
                            @foreach([
                                ['l' => 'Registratsiya', 's' => $rps, 't' => 'registration', 'sl' => ['passport_accepted' => 'Pasport olindi', 'registering' => 'Qilinmoqda', 'done' => 'Tugallandi'], 'btn' => ['passport_accepted' => 'Qilinmoqda', 'registering' => 'Yangilandi']],
                                ['l' => 'Viza', 's' => $vps, 't' => 'visa', 'sl' => ['passport_accepted' => 'Pasport olindi', 'registering' => 'Yangilanmoqda', 'done' => 'Tugallandi'], 'btn' => ['passport_accepted' => 'Yangilanmoqda', 'registering' => 'Yangilandi']],
                            ] as $p)
                                @continue($p['t'] === 'registration' && !$visaInfo->needsRegistration())
                                @continue($p['t'] === 'visa' && !$visaInfo->needsVisa())
                                <li class="sp-row">
                                    <div>
                                        <div class="sp-row-title">{{ $p['l'] }}</div>
                                        <span class="sp-pill {{ match($p['s']) { 'passport_accepted' => 'sp-pill-info', 'registering' => 'sp-pill-warn', 'done' => 'sp-pill-ok', default => '' } }}">{{ $p['sl'][$p['s']] ?? 'Kutilmoqda' }}</span>
                                    </div>
                                    @if($p['s'] === 'none' || $p['s'] === 'done')
                                        <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}">
                                            <button type="submit" class="sp-btn sp-btn-ghost" onclick="return confirm('Pasport qabul qilinsinmi?')">Pasport olish</button>
                                        </form>
                                    @elseif($p['s'] === 'passport_accepted')
                                        <form method="POST" action="{{ route('admin.international-students.mark-registering', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}">
                                            <button type="submit" class="sp-btn sp-btn-ghost">{{ $p['btn']['passport_accepted'] }}</button>
                                        </form>
                                    @elseif($p['s'] === 'registering')
                                        <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $p['t'] }}">
                                            <button type="submit" class="sp-btn sp-btn-ghost" onclick="return confirm('Pasport qaytarilsinmi?')">{{ $p['btn']['registering'] }}</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                            @if(!$visaInfo->needsRegistration() && !$visaInfo->needsVisa())
                                <li class="sp-row sp-faint">Yashash guvohnomasi bor — registratsiya va viza jarayoni yo'q.</li>
                            @endif
                        </ul>
                    </section>

                    <section class="sp-card" x-data="{ firm: @js($visaInfo->firm ?? '') }">
                        <div class="sp-card-head"><h2 class="sp-h2">Firma</h2></div>
                        <form method="POST" action="{{ route('admin.international-students.assign-firm', $student) }}" class="sp-card-body">
                            @csrf
                            <label for="firm" class="sp-sr">Firma</label>
                            <div class="sp-actions">
                                <select id="firm" name="firm" x-model="firm" class="sp-input" style="flex:1;" required>
                                    <option value="">Tanlang</option>
                                    @foreach(\App\Services\VisaSettings::firmOptions() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                    <option value="other">Boshqa</option>
                                </select>
                                <button type="submit" class="sp-btn sp-btn-ghost">Saqlash</button>
                            </div>
                            <input x-show="firm === 'other'" x-cloak type="text" name="firm_custom" value="{{ $visaInfo->firm_custom }}" class="sp-input" style="margin-top:8px;" placeholder="Firma nomi" aria-label="Firma nomi">
                        </form>
                    </section>
            </div>

            {{-- Hujjatlar — to'liq kenglikda, ikki ustun --}}
            @include('admin.international-students._documents', ['docState' => $deadline])

            {{-- Tahrirlash --}}
            <section class="sp-card" x-data="{ open: false }">
                <button type="button" class="sp-disclosure" @click="open = !open" :aria-expanded="open">
                    <span class="sp-h2">Ma'lumotlarni tahrirlash</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" :style="open && 'transform:rotate(180deg)'" style="transition:transform .15s;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div x-show="open" x-cloak class="sp-card-body" style="border-top:1px solid var(--sp-line);">
                    @include('admin.international-students._visa-form', ['student' => $student, 'visaInfo' => $visaInfo])
                </div>
            </section>

            {{-- Tarix --}}
            <section id="tarix-section" class="sp-card" x-data="{ open: {{ $history->isNotEmpty() ? 'true' : 'false' }} }">
                <button type="button" class="sp-disclosure" @click="open = !open" :aria-expanded="open">
                    <span class="sp-h2">O'zgarishlar tarixi <span class="sp-faint" style="font-weight:400;">{{ $history->count() }} ta yozuv</span></span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" :style="open && 'transform:rotate(180deg)'" style="transition:transform .15s;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div x-show="open" x-cloak class="sp-card-body" style="border-top:1px solid var(--sp-line);">
                    @include('admin.international-students._history-list', ['history' => $history, 'student' => $student])
                </div>
            </section>

            <form method="POST" action="{{ route('admin.international-students.destroy-visa-info', $student) }}" class="sp-danger">
                        @csrf @method('DELETE')
                        <button type="submit" class="sp-link-bad" onclick="return confirm('Talabaning viza ma\'lumotlari o\'chirilsinmi? Tarix saqlanib qoladi.')">Viza ma'lumotlarini o'chirish</button>
                    </form>
        @endif
    </div>

<style>
    .sp-page, .sp-topbar {
        --sp-ink: #0f172a; --sp-ink-2: #334155; --sp-muted: #64748b; --sp-faint: #94a3b8;
        --sp-line: #e5e7eb; --sp-line-2: #f1f5f9; --sp-surface: #fff; --sp-accent: #4f46e5;
        --sp-ok: #15803d; --sp-ok-bg: #f0fdf4; --sp-ok-line: #bbf7d0;
        --sp-warn: #b45309; --sp-warn-bg: #fffbeb; --sp-warn-line: #fde68a;
        --sp-bad: #b91c1c; --sp-bad-bg: #fef2f2; --sp-bad-line: #fecaca;
        --sp-info: #1d4ed8; --sp-info-bg: #eff6ff; --sp-info-line: #bfdbfe;
    }
    [x-cloak] { display: none !important; }

    /* Shell: max 1200, bir xil 16px oraliq — 2 ustun: 1fr + 360 + 16 gap */
    .sp-page { max-width: 1200px; margin: 0 auto; padding: 20px 16px 48px; display: flex; flex-direction: column; gap: 16px; color: var(--sp-ink); }
    .sp-topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .sp-topbar form { margin: 0; }
    .sp-crumbs { display: flex; align-items: center; gap: 8px; font-size: 13px; min-width: 0; }
    .sp-crumb-link { display: inline-flex; align-items: center; gap: 6px; color: var(--sp-muted); text-decoration: none; white-space: nowrap; }
    .sp-crumb-link:hover { color: var(--sp-ink); }
    .sp-crumb-sep { color: var(--sp-line); }
    .sp-crumb-current { color: var(--sp-ink); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .sp-flash { padding: 10px 14px; border-radius: 8px; font-size: 13px; border: 1px solid; }
    .sp-flash-ok { background: var(--sp-ok-bg); border-color: var(--sp-ok-line); color: var(--sp-ok); }
    .sp-flash-bad { background: var(--sp-bad-bg); border-color: var(--sp-bad-line); color: var(--sp-bad); }

    .sp-identity { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; padding: 4px 0; }
    .sp-identity-main { display: flex; gap: 14px; align-items: flex-start; min-width: 0; }
    .sp-identity-side { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
    .sp-flag-lg { width: 36px; height: 27px; border-radius: 3px; box-shadow: 0 0 0 1px rgba(15,23,42,.12); object-fit: cover; flex: none; margin-top: 3px; }
    .sp-flag-empty { display: inline-flex; align-items: center; justify-content: center; background: var(--sp-line-2); color: var(--sp-muted); font-weight: 600; font-size: 14px; }
    .sp-flag-sm { width: 18px; height: 14px; border-radius: 2px; box-shadow: 0 0 0 1px rgba(15,23,42,.12); object-fit: cover; flex: none; }
    .sp-title { font-size: 22px; line-height: 1.25; font-weight: 600; letter-spacing: -0.01em; margin: 0; }
    .sp-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 4px; font-size: 14px; color: var(--sp-ink-2); }
    .sp-meta b { font-weight: 600; color: var(--sp-ink); }
    .sp-meta-sub { font-size: 13px; color: var(--sp-muted); gap: 14px; }
    .sp-dot { color: var(--sp-faint); }
    .sp-pills { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }

    .sp-card { background: var(--sp-surface); border: 1px solid var(--sp-line); border-radius: 12px; }
    .sp-card-accent { border-color: var(--sp-warn-line); }
    .sp-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; padding: 14px 16px 0; }
    .sp-card-body { padding: 12px 16px 16px; }
    .sp-h2 { font-size: 14px; font-weight: 600; margin: 0; color: var(--sp-ink); }

    .sp-strip { display: grid; grid-template-columns: repeat(var(--sp-cols), 1fr); }
    .sp-strip-cell { padding: 14px 16px; border-left: 1px solid var(--sp-line); }
    .sp-strip-cell:first-child { border-left: 0; }
    .sp-label { display: block; font-size: 12px; color: var(--sp-muted); margin-bottom: 4px; }
    .sp-figure { font-size: 22px; font-weight: 600; letter-spacing: -0.01em; font-variant-numeric: tabular-nums; }
    .sp-figure-empty { color: var(--sp-faint); }
    .sp-state { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 4px; font-size: 13px; }
    .sp-tone-ok { color: var(--sp-muted); }
    .sp-tone-info { color: var(--sp-ink-2); }
    .sp-tone-warn { color: var(--sp-warn); font-weight: 500; }
    .sp-tone-bad { color: var(--sp-bad); font-weight: 600; }
    .sp-tone-none { color: var(--sp-faint); }

    .sp-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
    /* Amallar qatori: 2-3 karta, kengligi teng */
    .sp-actions-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; align-items: start; }
    .sp-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

    .sp-kv { margin: 0; padding: 8px 16px 14px; display: grid; gap: 0; }
    .sp-kv > div { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--sp-line-2); font-size: 14px; }
    .sp-kv > div:last-child { border-bottom: 0; }
    .sp-kv-2 { grid-template-columns: 1fr 1fr; column-gap: 32px; }
    .sp-kv-2 > .sp-kv-wide { grid-column: 1 / -1; grid-template-columns: 150px minmax(0, 1fr); }
    .sp-kv-2 > .sp-kv-wide dd { max-width: 640px; }
    .sp-kv dt { color: var(--sp-muted); font-size: 13px; }
    .sp-kv dd { margin: 0; color: var(--sp-ink); min-width: 0; overflow-wrap: anywhere; }
    .sp-split { display: grid; grid-template-columns: 1fr 1fr; }
    .sp-split > div + div { border-left: 1px solid var(--sp-line); }
    .sp-pad { padding: 8px 16px 16px; margin: 0; font-size: 14px; }

    .sp-num { font-variant-numeric: tabular-nums; }
    .sp-mono { font-variant-numeric: tabular-nums; letter-spacing: .02em; }
    .sp-strong { font-weight: 600; }
    .sp-faint { color: var(--sp-muted); font-size: 13px; }
    .sp-block { display: block; margin-top: 2px; }
    .sp-inline { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }

    .sp-pill { display: inline-flex; align-items: center; height: 22px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 500; white-space: nowrap; flex: none;
               background: var(--sp-line-2); color: var(--sp-ink-2); border: 1px solid var(--sp-line); }
    .sp-pill-ok { background: var(--sp-ok-bg); color: var(--sp-ok); border-color: var(--sp-ok-line); }
    .sp-pill-warn { background: var(--sp-warn-bg); color: var(--sp-warn); border-color: var(--sp-warn-line); }
    .sp-pill-bad { background: var(--sp-bad-bg); color: var(--sp-bad); border-color: var(--sp-bad-line); }
    .sp-pill-info { background: var(--sp-info-bg); color: var(--sp-info); border-color: var(--sp-info-line); }

    .sp-list { list-style: none; margin: 0; padding: 6px 16px 10px; }
    .sp-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--sp-line-2); font-size: 14px; }
    .sp-row:last-child { border-bottom: 0; }
    .sp-row form { margin: 0; }
    .sp-row-title { font-weight: 500; margin-bottom: 4px; }
    .sp-ico { color: var(--sp-ink-2); flex: none; }
    .sp-ico-faint { color: var(--sp-faint); flex: none; }

    .sp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid transparent; white-space: nowrap; transition: background-color .15s, border-color .15s; }
    .sp-btn:focus-visible, .sp-input:focus-visible, .sp-link:focus-visible, .sp-disclosure:focus-visible { outline: 2px solid var(--sp-accent); outline-offset: 2px; }
    .sp-btn-primary { background: var(--sp-ink); color: #fff; }
    .sp-btn-primary:hover { background: #1e293b; }
    .sp-btn-ghost { background: var(--sp-surface); color: var(--sp-ink); border-color: var(--sp-line); }
    .sp-btn-ghost:hover { background: var(--sp-line-2); }
    .sp-btn-ghost-bad { background: var(--sp-surface); color: var(--sp-bad); border-color: var(--sp-bad-line); }
    .sp-btn-ghost-bad:hover { background: var(--sp-bad-bg); }
    .sp-btn-bad { background: var(--sp-bad); color: #fff; }
    .sp-actions { display: flex; gap: 8px; align-items: center; }
    .sp-input { width: 100%; height: 34px; padding: 0 10px; border: 1px solid var(--sp-line); border-radius: 8px; font-size: 13px; color: var(--sp-ink); background: var(--sp-surface); }
    textarea.sp-input { height: auto; padding: 8px 10px; resize: vertical; }
    .sp-note { padding: 8px 10px; border-radius: 8px; font-size: 13px; margin-bottom: 10px; }
    .sp-note-bad { background: var(--sp-bad-bg); color: var(--sp-bad); }
    .sp-link { color: var(--sp-accent); font-size: 13px; font-weight: 500; text-decoration: none; }
    .sp-link:hover { text-decoration: underline; }
    .sp-link-quiet { color: var(--sp-muted); text-decoration: none; }
    .sp-link-quiet:hover { color: var(--sp-ink); text-decoration: underline; }
    .sp-danger { margin: 0; padding: 0 4px; }
    .sp-link-bad { background: none; border: 0; padding: 0; color: var(--sp-bad); font-size: 13px; cursor: pointer; }
    .sp-link-bad:hover { text-decoration: underline; }
    .sp-disclosure { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 14px 16px; background: none; border: 0; cursor: pointer; text-align: left; color: var(--sp-muted); border-radius: 12px; }
    .sp-empty { padding: 20px 16px; }
    .sp-empty-title { font-weight: 600; margin-bottom: 4px; }
    .sp-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

    @media (max-width: 1023px) {
        .sp-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 720px) {
        .sp-strip { grid-template-columns: 1fr 1fr; }
        .sp-strip-cell { border-left: 0; border-top: 1px solid var(--sp-line); }
        .sp-strip-cell:nth-child(-n+2) { border-top: 0; }
        .sp-strip-cell:nth-child(even) { border-left: 1px solid var(--sp-line); }
        .sp-kv-2, .sp-split { grid-template-columns: 1fr; }
        .sp-split > div + div { border-left: 0; border-top: 1px solid var(--sp-line); }
        .sp-strip-cell:last-child:nth-child(odd) { grid-column: 1 / -1; }
        .sp-kv-2 > .sp-kv-wide { grid-template-columns: 1fr; }
        .sp-kv > div { grid-template-columns: 1fr; gap: 2px; }
        .sp-identity-side { align-items: flex-start; }
        .sp-crumb-link span, .sp-crumb-sep, .sp-crumb-current { display: none; }
    }
    @media (prefers-reduced-motion: reduce) { .sp-btn, .sp-disclosure svg { transition: none !important; } }
</style>
</x-app-layout>
