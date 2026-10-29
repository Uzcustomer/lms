<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Vedomost — AI sozlamalari</h2>
    </x-slot>

    @php
        $s = $settings;
        $p = $stats['periods'];
        $errorLabels = [
            'rate_limit' => "So'rovlar ko'p (429)",
            'quota' => 'Kvota tugagan (429)',
            'overloaded' => 'Gemini band (503)',
            'server' => 'Gemini server xatosi',
            'timeout' => 'Timeout / ulanish',
            'auth' => 'Kalit yaroqsiz',
            'model_not_found' => 'Model topilmadi',
            'bad_request' => "Noto'g'ri so'rov",
            'truncated' => 'Javob kesilgan',
            'invalid_json' => 'Yaroqsiz javob',
            'blocked' => 'Bloklangan',
        ];
        $fmtMs = fn ($ms) => $ms === null ? '—' : ($ms >= 1000 ? number_format($ms / 1000, 1) . ' s' : $ms . ' ms');
        $fmtNum = fn ($n) => number_format((int) $n, 0, '.', ' ');
        $card = 'background:#fff;border:1px solid #f1f5f9;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);padding:18px;margin-top:14px;';
        $label = 'display:block;font-size:12px;color:#475569;font-weight:600;margin-bottom:4px;';
        $input = 'width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:7px 10px;font-size:14px;';
        $hint = 'font-size:11px;color:#94a3b8;margin-top:3px;';
        $maxDay = max(1, collect($stats['days'])->max('total'));
        $hasFreeKeys = $keys->contains(fn ($k) => !$k->is_paid && $k->enabled);
    @endphp

    <div class="py-4">
        <div class="max-w-6xl mx-auto sm:px-4 lg:px-6">
            <a href="{{ route('admin.vedomost-submission.index') }}" style="color:#64748b;text-decoration:none;font-size:13px;">← Vedomost topshirish</a>

            @if(session('success'))
                <div style="background:#dcfce7;color:#166534;padding:10px 16px;border-radius:8px;margin:12px 0;border:1px solid #bbf7d0;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div style="background:#fee2e2;color:#b91c1c;padding:10px 16px;border-radius:8px;margin:12px 0;border:1px solid #fecaca;">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div style="background:#fee2e2;color:#b91c1c;padding:10px 16px;border-radius:8px;margin:12px 0;border:1px solid #fecaca;">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Holat va navbat --}}
            <div style="{{ $card }}display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="font-size:14px;color:#334155;">
                    @if($configured)
                        <span style="background:#dcfce7;color:#166534;padding:4px 12px;border-radius:999px;font-weight:700;">✅ AI tekshiruv ishlaydi</span>
                    @else
                        <span style="background:#fee2e2;color:#b91c1c;padding:4px 12px;border-radius:999px;font-weight:700;">❌ Ishlatsa bo'ladigan Gemini kaliti yo'q</span>
                    @endif
                    <span style="margin-left:10px;">Avtomatik tekshirish: <b>{{ $s['auto_check'] ? 'yoqilgan' : "o'chirilgan (qo'lda)" }}</b></span>
                    <span style="margin-left:10px;">Navbatda/ishlamoqda: <b>{{ $stats['outcomes']['in_progress'] }}</b></span>
                </div>
                <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.queue-pending') }}" style="display:flex;gap:8px;align-items:center;"
                      onsubmit="return confirm('Tekshirilmagan vedomostlar AI navbatiga qo\'yilsinmi?');">
                    @csrf
                    <input type="number" name="max" min="1" placeholder="hammasi" style="width:100px;border:1px solid #cbd5e1;border-radius:8px;padding:7px 10px;font-size:13px;" title="Eng ko'pi bilan nechta">
                    <button type="submit" @disabled(!$configured || $stats['outcomes']['pending'] === 0)
                            style="background:#6d28d9;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;{{ (!$configured || $stats['outcomes']['pending'] === 0) ? 'opacity:.5;cursor:not-allowed;' : '' }}">
                        Tekshirilmaganlarni navbatga qo'shish ({{ $stats['outcomes']['pending'] }})
                    </button>
                </form>
            </div>

            {{-- Statistika --}}
            <div style="{{ $card }}">
                <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:12px;">📊 So'rovlar statistikasi</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
                    @foreach(['today' => 'Bugun', 'week' => '7 kun', 'month' => '30 kun', 'all' => 'Jami'] as $k => $t)
                        <div style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;">
                            <div style="font-size:12px;color:#64748b;font-weight:600;">{{ $t }}</div>
                            <div style="font-size:24px;font-weight:800;color:#1e293b;">{{ $fmtNum($p[$k]['total']) }}</div>
                            <div style="font-size:12px;color:#475569;">
                                <span style="color:#166534;">✓ {{ $fmtNum($p[$k]['ok']) }}</span>
                                · <span style="color:#b91c1c;">✕ {{ $fmtNum($p[$k]['errors']) }}</span>
                            </div>
                            <div style="font-size:12px;color:#475569;margin-top:4px;">
                                Javob: o'rtacha <b>{{ $fmtMs($p[$k]['avg_ms']) }}</b>, eng uzun {{ $fmtMs($p[$k]['max_ms']) }}
                            </div>
                            <div style="font-size:12px;color:#475569;">
                                Token: {{ $fmtNum($p[$k]['input_tokens']) }} kirish / {{ $fmtNum($p[$k]['output_tokens']) }} chiqish
                            </div>
                            @if($p[$k]['cost'] !== null)
                                <div style="font-size:12px;color:#475569;">Taxminiy narx: <b>${{ number_format($p[$k]['cost'], 4) }}</b></div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- 14 kunlik --}}
                <div style="margin-top:16px;">
                    <div style="font-size:12px;color:#64748b;font-weight:600;margin-bottom:6px;">Oxirgi 14 kun (so'rovlar; to'q — muvaffaqiyatli)</div>
                    <div style="display:flex;align-items:flex-end;gap:4px;height:90px;border-bottom:1px solid #e2e8f0;">
                        @foreach($stats['days'] as $d)
                            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;height:100%;" title="{{ \Carbon\Carbon::parse($d['date'])->format('d.m') }}: {{ $d['total'] }} ta ({{ $d['ok'] }} muvaffaqiyatli)">
                                <div style="background:#fca5a5;height:{{ round(($d['total'] - $d['ok']) / $maxDay * 100) }}%;"></div>
                                <div style="background:#6d28d9;height:{{ round($d['ok'] / $maxDay * 100) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div style="display:flex;gap:4px;">
                        @foreach($stats['days'] as $d)
                            <div style="flex:1;text-align:center;font-size:10px;color:#94a3b8;">{{ \Carbon\Carbon::parse($d['date'])->format('d') }}</div>
                        @endforeach
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:16px;">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#334155;margin-bottom:6px;">AI tekshiruv natijalari (vedomostlar)</div>
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <tr><td style="padding:4px 0;">✅ Nomuvofiqlik yo'q</td><td style="text-align:right;font-weight:700;">{{ $stats['outcomes']['ok'] }}</td></tr>
                            <tr><td style="padding:4px 0;">⚠️ Nomuvofiqlik topilgan</td><td style="text-align:right;font-weight:700;">{{ $stats['outcomes']['issues'] }}</td></tr>
                            <tr><td style="padding:4px 0;">❌ Tekshiruv xatosi</td><td style="text-align:right;font-weight:700;">{{ $stats['outcomes']['error'] }}</td></tr>
                            <tr><td style="padding:4px 0;">⏳ Navbatda / ishlamoqda</td><td style="text-align:right;font-weight:700;">{{ $stats['outcomes']['in_progress'] }}</td></tr>
                            <tr><td style="padding:4px 0;">○ Tekshirilmagan (tasdiqlanmagan)</td><td style="text-align:right;font-weight:700;">{{ $stats['outcomes']['pending'] }}</td></tr>
                        </table>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#334155;margin-bottom:6px;">Xato turlari (30 kun)</div>
                        @forelse($stats['errorTypes'] as $type => $cnt)
                            <div style="display:flex;justify-content:space-between;font-size:13px;padding:3px 0;">
                                <span>{{ $errorLabels[$type] ?? ($type ?: "noma'lum") }}</span><b>{{ $cnt }}</b>
                            </div>
                        @empty
                            <div style="font-size:13px;color:#94a3b8;">Xato yo'q.</div>
                        @endforelse
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#334155;margin-bottom:6px;">Modellar (30 kun)</div>
                        @forelse($stats['models'] as $m)
                            <div style="display:flex;justify-content:space-between;font-size:13px;padding:3px 0;gap:8px;">
                                <span>{{ $m->model }}</span>
                                <span>{{ (int) $m->ok }}/{{ (int) $m->total }} · {{ $fmtMs($m->avg_ms !== null ? (int) round($m->avg_ms) : null) }}</span>
                            </div>
                        @empty
                            <div style="font-size:13px;color:#94a3b8;">Hali so'rov yo'q.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Sozlamalar --}}
            <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.save') }}" style="{{ $card }}">
                @csrf
                <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:12px;">⚙️ Sozlamalar</h3>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                    <div>
                        <label style="{{ $label }}">Asosiy model</label>
                        <input name="model" list="ai-models" value="{{ old('model', $s['model']) }}" style="{{ $input }}" required>
                        <div style="{{ $hint }}">Masalan: gemini-2.5-flash</div>
                    </div>
                    <div>
                        <label style="{{ $label }}">Zaxira model</label>
                        <input name="fallback_model" list="ai-models" value="{{ old('fallback_model', $s['fallback_model']) }}" style="{{ $input }}">
                        <div style="{{ $hint }}">Asosiy model ishlamasa (404, uzoq 503)</div>
                    </div>
                    <div>
                        <label style="{{ $label }}">Javob kutish vaqti (soniya)</label>
                        <input type="number" name="timeout" min="10" max="600" value="{{ old('timeout', $s['timeout']) }}" style="{{ $input }}" required>
                    </div>
                    <div>
                        <label style="{{ $label }}">Qayta urinishlar soni</label>
                        <input type="number" name="max_retries" min="0" max="5" value="{{ old('max_retries', $s['max_retries']) }}" style="{{ $input }}" required>
                        <div style="{{ $hint }}">429/503 bo'lsa barcha kalitlar bo'yicha yana necha marta</div>
                    </div>
                    <div>
                        <label style="{{ $label }}">Thinking budget (token)</label>
                        <input type="number" name="thinking_budget" min="0" max="32768" value="{{ old('thinking_budget', $s['thinking_budget']) }}" style="{{ $input }}" placeholder="model standarti">
                        <div style="{{ $hint }}">Bo'sh — model standarti; 0 — o'ylashsiz (tez, arzon)</div>
                    </div>
                    <div>
                        <label style="{{ $label }}">Sahifani bo'laklarga bo'lish</label>
                        <input type="number" name="bands" min="1" max="4" value="{{ old('bands', $s['bands']) }}" style="{{ $input }}" required>
                        <div style="{{ $hint }}">1 — butun sahifa bitta so'rovda; ichki hisob mos kelmasa avtomatik +1 bo'lak bilan qayta o'qiladi</div>
                        @unless($renderer)
                            <div style="font-size:11px;color:#b45309;margin-top:3px;">⚠️ Serverda pdftoppm yo'q — PDF butunligicha yuboriladi (bo'laklash va qayta o'qish ishlamaydi). Docker image'ni qayta quring.</div>
                        @endunless
                    </div>
                    <div>
                        <label style="{{ $label }}">Kunlik umumiy so'rov limiti</label>
                        <input type="number" name="daily_limit" min="1" value="{{ old('daily_limit', $s['daily_limit']) }}" style="{{ $input }}" placeholder="cheksiz">
                        <div style="{{ $hint }}">Barcha kalitlar bo'yicha jami (kalit limitlari alohida)</div>
                    </div>
                    <div>
                        <label style="{{ $label }}">Narx: 1M kirish tokeni ($)</label>
                        <input type="number" step="0.0001" min="0" name="price_input_per_m" value="{{ old('price_input_per_m', $s['price_input_per_m']) }}" style="{{ $input }}" placeholder="—">
                    </div>
                    <div>
                        <label style="{{ $label }}">Narx: 1M chiqish tokeni ($)</label>
                        <input type="number" step="0.0001" min="0" name="price_output_per_m" value="{{ old('price_output_per_m', $s['price_output_per_m']) }}" style="{{ $input }}" placeholder="—">
                        <div style="{{ $hint }}">Statistikadagi taxminiy narx uchun</div>
                    </div>
                </div>

                <datalist id="ai-models">
                    @foreach($models as $m)
                        <option value="{{ $m['name'] }}">{{ $m['label'] }}</option>
                    @endforeach
                </datalist>

                <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px;font-size:14px;color:#334155;">
                    <label style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="auto_check" value="1" @checked(old('auto_check', $s['auto_check']))>
                        PDF yuklanishi bilan AI tekshiruvni <b>avtomatik</b> boshlash (o'chiq bo'lsa — vedomost sahifasida qo'lda)
                    </label>
                    <label style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="require_ai_before_approve" value="1" @checked(old('require_ai_before_approve', $s['require_ai_before_approve']))>
                        Tasdiqlashdan oldin AI tekshiruvi talab qilinsin (AI xato topsa yoki tekshirmagan bo'lsa — xodim sabab yozib tasdiqlaydi)
                    </label>
                    <label style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="allow_free_keys" value="1" @checked(old('allow_free_keys', $s['allow_free_keys']))>
                        Bepul kalitlardan ham foydalanish (pullik kalitlar har doim birinchi)
                    </label>
                    <div style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:8px;padding:8px 12px;font-size:12px;">
                        Diqqat: vedomostda talabalarning F.I.Sh, ID raqami va baholari bor. Google bepul Gemini API tarifida
                        yuborilgan ma'lumotdan o'z mahsulotlarini yaxshilash uchun foydalanishi mumkin. Pullik (billing yoqilgan) kalitlarda bunday emas.
                    </div>
                </div>

                <h4 style="font-size:14px;font-weight:700;color:#1e293b;margin:18px 0 10px;">Tekshiruv qoidalari</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                    <div>
                        <label style="{{ $label }}">JN/MT o'tish chegarasi (%)</label>
                        <input type="number" name="jn_mt_pass" min="0" max="100" value="{{ old('jn_mt_pass', $s['jn_mt_pass']) }}" style="{{ $input }}" required>
                        @if((int) $s['jn_mt_pass'] !== (int) $systemPass)
                            <div style="font-size:11px;color:#b45309;margin-top:3px;">⚠️ Tizimda (jurnal) {{ $systemPass }} ishlatiladi — farq soxta nomuvofiqlik chiqarishi mumkin</div>
                        @endif
                    </div>
                    <div>
                        <label style="{{ $label }}">JN/MT chegarasi — xalqaro fakultet (%)</label>
                        <input type="number" name="jn_mt_pass_international" min="0" max="100" value="{{ old('jn_mt_pass_international', $s['jn_mt_pass_international']) }}" style="{{ $input }}" required>
                        @if((int) $s['jn_mt_pass_international'] !== (int) $systemPassIntl)
                            <div style="font-size:11px;color:#b45309;margin-top:3px;">⚠️ Tizimda {{ $systemPassIntl }} ishlatiladi</div>
                        @endif
                    </div>
                    <div>
                        <label style="{{ $label }}">OSKE minimal (%) — Testga kirish</label>
                        <input type="number" name="oski_min" min="0" max="100" value="{{ old('oski_min', $s['oski_min']) }}" style="{{ $input }}" required>
                        <div style="{{ $hint }}">OSKE+Test fanlarida OSKE shundan past bo'lsa Test bahosi bo'lmasligi kerak</div>
                    </div>
                    <div>
                        <label style="{{ $label }}">Test minimal (%)</label>
                        <input type="number" name="test_min" min="0" max="100" value="{{ old('test_min', $s['test_min']) }}" style="{{ $input }}" required>
                    </div>
                    <div>
                        <label style="{{ $label }}">Ruxsat etilgan farq (son)</label>
                        <input type="number" step="0.01" name="tolerance" min="0" max="5" value="{{ old('tolerance', $s['tolerance']) }}" style="{{ $input }}" required>
                        <div style="{{ $hint }}">PDF va tizim qiymati shu qadar farq qilsa — mos deb hisoblanadi</div>
                    </div>
                </div>

                <div style="margin-top:16px;display:flex;gap:8px;">
                    <button type="submit" style="background:#1a3268;color:#fff;border:none;padding:9px 20px;border-radius:8px;cursor:pointer;font-weight:600;">Saqlash</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.models') }}" style="margin-top:8px;">
                @csrf
                <button type="submit" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:13px;">
                    ↻ Modellar ro'yxatini Gemini'dan yangilash ({{ count($models) }} ta saqlangan)
                </button>
            </form>

            {{-- Kalitlar (faqat admin) --}}
            @if($canManageKeys)
                <div style="{{ $card }}">
                    <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:12px;">🔑 Gemini API kalitlari</h3>

                    @if($hasFreeKeys && $s['allow_free_keys'])
                        <div style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:8px;padding:8px 12px;font-size:12px;margin-bottom:10px;">
                            Bepul kalitlar yoqilgan — pullik kalitlar ishlamay qolsa yoki limiti tugasa, so'rovlar bepul kalitlarga ketadi.
                        </div>
                    @endif

                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#f8fafc;text-align:left;">
                                    <th style="padding:6px;">Kalit</th>
                                    <th style="padding:6px;">Nomi</th>
                                    <th style="padding:6px;">Turi</th>
                                    <th style="padding:6px;">RPM</th>
                                    <th style="padding:6px;">Kunlik limit</th>
                                    <th style="padding:6px;">Bugun / 7 kun</th>
                                    <th style="padding:6px;">Yoqilgan</th>
                                    <th style="padding:6px;">Oxirgi xato</th>
                                    <th style="padding:6px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($keys as $k)
                                    @php $u = $keyUsage[$k->id] ?? []; $fid = 'key-form-' . $k->id; @endphp
                                    <tr style="border-top:1px solid #f1f5f9;vertical-align:top;">
                                        <td style="padding:6px;font-family:monospace;">{{ $k->key_hint }}</td>
                                        <td style="padding:6px;"><input form="{{ $fid }}" name="label" value="{{ $k->label }}" style="{{ $input }}width:130px;"></td>
                                        <td style="padding:6px;white-space:nowrap;">
                                            <label><input form="{{ $fid }}" type="checkbox" name="is_paid" value="1" @checked($k->is_paid)> pullik</label>
                                        </td>
                                        <td style="padding:6px;"><input form="{{ $fid }}" type="number" name="rpm" min="1" value="{{ $k->rpm }}" placeholder="∞" style="{{ $input }}width:80px;"></td>
                                        <td style="padding:6px;"><input form="{{ $fid }}" type="number" name="daily_limit" min="1" value="{{ $k->daily_limit }}" placeholder="∞" style="{{ $input }}width:90px;"></td>
                                        <td style="padding:6px;white-space:nowrap;">
                                            {{ $u['today'] ?? 0 }}@if($k->daily_limit) / {{ $k->daily_limit }}@endif
                                            @if(!empty($u['today_errors']))<span style="color:#b91c1c;">({{ $u['today_errors'] }} xato)</span>@endif
                                            · {{ $u['week'] ?? 0 }}
                                        </td>
                                        <td style="padding:6px;"><input form="{{ $fid }}" type="checkbox" name="enabled" value="1" @checked($k->enabled)></td>
                                        <td style="padding:6px;max-width:260px;font-size:12px;color:#b91c1c;">
                                            @if($k->last_error)
                                                <span title="{{ $k->last_error }}">{{ \Illuminate\Support\Str::limit($k->last_error, 80) }}</span>
                                                <div style="color:#94a3b8;">{{ $k->last_error_at?->format('d.m.Y H:i') }}</div>
                                            @else
                                                <span style="color:#94a3b8;">—</span>
                                            @endif
                                        </td>
                                        <td style="padding:6px;white-space:nowrap;">
                                            <form id="{{ $fid }}" method="POST" action="{{ route('admin.vedomost-submission.ai-settings.keys.update', $k->id) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" style="background:#1a3268;color:#fff;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:12px;">Saqlash</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.keys.destroy', $k->id) }}" style="display:inline;"
                                                  onsubmit="return confirm('Kalit o\'chirilsinmi?');">
                                                @csrf
                                                <button type="submit" style="background:#fee2e2;color:#b91c1c;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:12px;">O'chirish</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" style="padding:10px;color:#94a3b8;">Hali kalit qo'shilmagan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.keys.store') }}"
                          style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end;margin-top:14px;border-top:1px solid #f1f5f9;padding-top:14px;">
                        @csrf
                        <div style="grid-column:span 2;">
                            <label style="{{ $label }}">Yangi API kalit</label>
                            <input type="password" name="api_key" autocomplete="off" required style="{{ $input }}" placeholder="AIza...">
                        </div>
                        <div>
                            <label style="{{ $label }}">Nomi</label>
                            <input name="label" style="{{ $input }}" placeholder="masalan: Asosiy">
                        </div>
                        <div>
                            <label style="{{ $label }}">RPM</label>
                            <input type="number" name="rpm" min="1" style="{{ $input }}" placeholder="∞">
                        </div>
                        <div>
                            <label style="{{ $label }}">Kunlik limit</label>
                            <input type="number" name="daily_limit" min="1" style="{{ $input }}" placeholder="∞">
                        </div>
                        <label style="display:flex;gap:6px;align-items:center;font-size:13px;padding-bottom:8px;">
                            <input type="checkbox" name="is_paid" value="1"> Pullik kalit
                        </label>
                        <div>
                            <button type="submit" style="background:#6d28d9;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;width:100%;">+ Qo'shish</button>
                        </div>
                    </form>
                    <div style="{{ $hint }}">Kalit bazada shifrlangan holda saqlanadi va qayta ko'rsatilmaydi — faqat oxirgi 4 belgisi.</div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
