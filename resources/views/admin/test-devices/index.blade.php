<x-app-layout>
<style>
    .td { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; color: #1e293b; }
    .td h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; color: #0f2748; }
    .td-lead { margin: 0 0 18px; color: #64748b; font-size: 14px; }
    .td-alert { margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 14px; }
    .td-alert.ok { background: #ecfdf5; border: 1px solid #86efac; color: #166534; }
    .td-alert.warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
    .td-alert.bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .td-panel { margin-bottom: 20px; padding: 18px 20px; border: 1px solid #dde5ef; border-radius: 10px; background: #fff; }
    .td-panel h2 { margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #0f2748; }
    .td-here { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .td-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .td-badge.ok { background: #dcfce7; color: #166534; }
    .td-badge.no { background: #f1f5f9; color: #64748b; }
    .td-badge.bad { background: #fee2e2; color: #b91c1c; }
    .td-form { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    .td-form input {
        flex: 1 1 260px; height: 42px; padding: 0 12px;
        border: 1px solid #c4d0e0; border-radius: 8px; font-family: inherit; font-size: 14px;
    }
    .td-btn {
        height: 42px; padding: 0 18px; border: 0; border-radius: 8px;
        background: #16a34a; color: #fff; font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer;
    }
    .td-btn:hover { background: #15803d; }
    .td-btn.ghost { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
    .td-btn.ghost:hover { background: #fef2f2; }
    .td-btn.sm { height: 32px; padding: 0 12px; font-size: 12.5px; }
    .td-table-wrap { overflow-x: auto; }
    .td-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .td-table th { padding: 9px 10px; border-bottom: 1px solid #dde5ef; color: #64748b; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
    .td-table td { padding: 10px; border-bottom: 1px solid #eef2f8; vertical-align: middle; }
    .td-table tr.is-revoked td { color: #94a3b8; }
    .td-muted { color: #94a3b8; font-size: 12px; }
    .td-num { font-variant-numeric: tabular-nums; }
</style>

<div class="td">
    <h1>Test kompyuterlari</h1>
    <p class="td-lead">Fan testi faqat shu ro'yxatdagi sinf kompyuterlarida ochiladi. Har bir kompyuterda shu sahifani ochib, tugmani bir marta bosing.</p>

    @if(session('success'))
        <div class="td-alert ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="td-alert bad">{{ $errors->first() }}</div>
    @endif
    @if(!$tableReady)
        <div class="td-alert bad">Jadval hali yaratilmagan — serverda <b>php artisan migrate</b> ni ishga tushiring.</div>
    @elseif(!$enforced)
        <div class="td-alert warn">Cheklov hozir <b>o'chiq</b>: test hamma joyda ochiladi. Kompyuterlarni ro'yxatdan o'tkazib bo'lgach, .env ga <b>FAN_TEST_REQUIRE_DEVICE=true</b> yozing va <b>php artisan config:clear</b> qiling.</div>
    @endif

    @if($tableReady)
        <div class="td-panel">
            <h2>Shu kompyuter</h2>
            <div class="td-here">
                @if($current)
                    <span class="td-badge ok">Ro'yxatdan o'tgan</span>
                    <b>{{ $current->name }}</b>
                    <span class="td-muted">{{ $current->created_at?->format('d.m.Y H:i') }} dan beri</span>
                @else
                    <span class="td-badge no">Ro'yxatdan o'tmagan</span>
                    <span class="td-muted">Bu kompyuterda fan testi ochilmaydi.</span>
                @endif
            </div>

            <form class="td-form" method="POST" action="{{ route('test-devices.store') }}">
                @csrf
                <input type="text" name="name" id="device-name" required maxlength="120"
                       value="{{ old('name', $current?->name) }}"
                       placeholder="Masalan: 2-bino, 305-xona, 7-kompyuter">
                <button type="submit" class="td-btn">
                    {{ $current ? 'Nomini saqlash' : 'Bu kompyuterni test kompyuteri sifatida belgilash' }}
                </button>
            </form>

            @if($current)
                <form method="POST" action="{{ route('test-devices.forget') }}" style="margin-top:10px"
                      onsubmit="return confirm('Bu kompyuter ro\'yxatdan chiqarilsinmi? Unda fan testi ochilmay qoladi.')">
                    @csrf
                    <button type="submit" class="td-btn ghost sm">Bu kompyuterni ro'yxatdan chiqarish</button>
                </form>
            @endif
            <p class="td-muted" style="margin:12px 0 0">Belgilagandan keyin LMS dan chiqishingiz mumkin — belgi shu brauzerda qoladi. Brauzer tozalansa yoki boshqa brauzer ishlatilsa, kompyuterni qayta belgilang.</p>
        </div>

        <div class="td-panel">
            <h2>Barcha kompyuterlar ({{ $devices->whereNull('revoked_at')->count() }} ta faol)</h2>
            @if($devices->isEmpty())
                <p class="td-muted" style="margin:0">Hali birorta kompyuter ro'yxatdan o'tmagan.</p>
            @else
                <div class="td-table-wrap">
                    <table class="td-table">
                        <thead>
                        <tr>
                            <th>Kompyuter</th>
                            <th>Holat</th>
                            <th>Belgilagan</th>
                            <th>Oxirgi ishlatilgan</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($devices as $device)
                            <tr class="{{ $device->isActive() ? '' : 'is-revoked' }}">
                                <td><b>{{ $device->name }}</b>@if($current && $current->id === $device->id) <span class="td-muted">(shu kompyuter)</span>@endif</td>
                                <td>
                                    @if($device->isActive())
                                        <span class="td-badge ok">Faol</span>
                                    @else
                                        <span class="td-badge bad" title="{{ $device->revoked_reason }}">Bekor qilingan</span>
                                        <div class="td-muted">{{ $device->revoked_reason }} · {{ $device->revoked_at?->format('d.m.Y H:i') }}</div>
                                    @endif
                                </td>
                                <td>{{ $device->registered_by_name ?? '—' }}<div class="td-muted td-num">{{ $device->created_at?->format('d.m.Y H:i') }}</div></td>
                                <td class="td-num">{{ $device->last_used_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td style="text-align:right">
                                    @if($device->isActive())
                                        <form method="POST" action="{{ route('test-devices.revoke', $device) }}"
                                              onsubmit="return confirm('{{ addslashes($device->name) }} bekor qilinsinmi?')">
                                            @csrf
                                            <button type="submit" class="td-btn ghost sm">Bekor qilish</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
</x-app-layout>
