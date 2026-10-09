<x-app-layout>
<style>
    .td { padding: 24px 16px 48px; color: #1e293b; }
    .td-in { max-width: 1200px; margin: 0 auto; }
    .td h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; color: #0f2748; }
    .td-lead { margin: 0 0 18px; color: #64748b; font-size: 14px; }
    .td-alert { margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 14px; }
    .td-alert.ok { background: #ecfdf5; border: 1px solid #86efac; color: #166534; }
    .td-alert.warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
    .td-alert.bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .td-alert code { padding: 1px 6px; border-radius: 4px; background: rgba(0,0,0,.06); font-size: 12.5px; }
    .td-panel { margin-bottom: 18px; padding: 18px 20px; border: 1px solid #dde5ef; border-radius: 12px; background: #fff; }
    .td-panel h2 { margin: 0 0 10px; font-size: 16px; font-weight: 700; color: #0f2748; }
    .td-here { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .td-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .td-badge.ok { background: #dcfce7; color: #166534; }
    .td-badge.no { background: #f1f5f9; color: #64748b; }
    .td-badge.bad { background: #fee2e2; color: #b91c1c; }
    .td-badge.run { background: #dbeafe; color: #1e40af; }
    .td-form { display: grid; grid-template-columns: 160px 1fr auto; gap: 10px; margin-top: 12px; }
    .td-form label { display: block; margin-bottom: 4px; color: #475569; font-size: 12px; font-weight: 700; }
    .td-form input {
        width: 100%; height: 42px; padding: 0 12px;
        border: 1px solid #c4d0e0; border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box;
    }
    .td-form .td-btn { align-self: end; }
    .td-btn {
        height: 42px; padding: 0 18px; border: 0; border-radius: 8px;
        background: #16a34a; color: #fff; font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer; white-space: nowrap;
    }
    .td-btn:hover { background: #15803d; }
    .td-btn.ghost { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
    .td-btn.ghost:hover { background: #fef2f2; }
    .td-btn.soft { background: #fff; color: #1e40af; border: 1px solid #bfdbfe; }
    .td-btn.soft:hover { background: #eff6ff; }
    .td-btn.sm { height: 30px; padding: 0 11px; font-size: 12.5px; }
    .td-muted { color: #94a3b8; font-size: 12px; }
    .td-room { margin-top: 14px; border: 1px solid #e5ebf3; border-radius: 10px; overflow: hidden; }
    .td-room-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #f5f8fc; border-bottom: 1px solid #e5ebf3; }
    .td-room-head b { color: #0f2748; font-size: 15px; }
    .td-table-wrap { overflow-x: auto; }
    .td-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .td-table th { padding: 8px 12px; border-bottom: 1px solid #eef2f8; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
    .td-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .td-table tr:last-child td { border-bottom: 0; }
    .td-dot { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: #cbd5e1; vertical-align: 1px; }
    .td-dot.on { background: #16a34a; }
    .td-num { font-variant-numeric: tabular-nums; }
    .td-acts { display: flex; gap: 6px; justify-content: flex-end; }
    @media (max-width: 720px) { .td-form { grid-template-columns: 1fr; } }
</style>

<div class="td"><div class="td-in">
    <h1>Test kompyuterlari</h1>
    <p class="td-lead">Har bir sinf kompyuterini xonasi bilan ro'yxatdan o'tkazing, so'ng o'sha kompyuterda kutish ekranini ochib qo'ying. O'qituvchi "Test yaratish" sahifasida xona bo'yicha kompyuterlarni tanlab testni boshlaydi.</p>

    @if(session('success'))
        <div class="td-alert ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="td-alert bad">{{ $errors->first() }}</div>
    @endif
    @if(!$tableReady || !$hasRooms)
        <div class="td-alert bad">Jadval yangilanmagan — serverda <code>php artisan migrate</code> ni ishga tushiring.</div>
    @elseif(!$enforced)
        <div class="td-alert warn">Cheklov hozir <b>o'chiq</b>: test havolasi hamma joyda ochiladi. Kompyuterlarni ro'yxatdan o'tkazib bo'lgach, .env ga <code>FAN_TEST_REQUIRE_DEVICE=true</code> yozing va <code>php artisan config:clear</code> qiling.</div>
    @endif

    @if($tableReady)
        <div class="td-panel">
            <h2>Shu kompyuter</h2>
            <div class="td-here">
                @if($current)
                    <span class="td-badge ok">Ro'yxatdan o'tgan</span>
                    <b>{{ $current->room ? $current->room . '-xona · ' : '' }}{{ $current->name }}</b>
                    <span class="td-muted">{{ $current->created_at?->format('d.m.Y H:i') }} dan beri</span>
                @else
                    <span class="td-badge no">Ro'yxatdan o'tmagan</span>
                    <span class="td-muted">Bu kompyuterda fan testi ochilmaydi.</span>
                @endif
            </div>

            <form class="td-form" method="POST" action="{{ route('test-devices.store') }}">
                @csrf
                @if($hasRooms)
                    <div>
                        <label for="device-room">Xona</label>
                        <input type="text" name="room" id="device-room" required maxlength="60"
                               value="{{ old('room', $current?->room) }}" placeholder="Masalan: 305">
                    </div>
                @endif
                <div>
                    <label for="device-name">Kompyuter nomi</label>
                    <input type="text" name="name" id="device-name" required maxlength="120"
                           value="{{ old('name', $current?->name) }}" placeholder="Masalan: 7-kompyuter">
                </div>
                <button type="submit" class="td-btn">
                    {{ $current ? 'Saqlash' : 'Bu kompyuterni test kompyuteri sifatida belgilash' }}
                </button>
            </form>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px">
                @if($current)
                    <a class="td-btn soft sm" style="display:inline-flex;align-items:center;text-decoration:none" href="{{ route('test-devices.wait') }}" target="_blank">Kutish ekranini ochish</a>
                    <form method="POST" action="{{ route('test-devices.forget') }}"
                          onsubmit="return confirm('Bu kompyuter ro\'yxatdan chiqarilsinmi? Unda fan testi ochilmay qoladi.')">
                        @csrf
                        <button type="submit" class="td-btn ghost sm">Bu kompyuterni ro'yxatdan chiqarish</button>
                    </form>
                @endif
            </div>
            <p class="td-muted" style="margin:12px 0 0">Belgilagandan keyin LMS dan chiqing va shu brauzerda <b>{{ route('test-devices.wait') }}</b> manzilini ochib qo'ying. Brauzer tozalansa yoki boshqa brauzer ishlatilsa, kompyuterni qayta belgilang.</p>
        </div>

        <div class="td-panel">
            <h2>Kompyuterlar xonalar bo'yicha ({{ $rooms->flatten()->count() }} ta faol)</h2>
            @forelse($rooms as $room => $items)
                <div class="td-room">
                    <div class="td-room-head">
                        <b>{{ $room === 'Xonasiz' ? 'Xona belgilanmagan' : $room . '-xona' }}</b>
                        <span class="td-muted">{{ $items->count() }} ta kompyuter · {{ $items->filter->isOnline()->count() }} ta onlayn</span>
                    </div>
                    <div class="td-table-wrap">
                        <table class="td-table">
                            <thead><tr><th>Kompyuter</th><th>Holat</th><th>Test</th><th>Belgilagan</th><th>Oxirgi faollik</th><th></th></tr></thead>
                            <tbody>
                            @foreach($items as $device)
                                <tr>
                                    <td><b>{{ $device->name }}</b>@if($current && $current->id === $device->id) <span class="td-muted">(shu kompyuter)</span>@endif</td>
                                    <td>
                                        <span class="td-dot {{ $device->isOnline() ? 'on' : '' }}"></span>{{ $device->isOnline() ? 'Kutish ekranida' : 'Oflayn' }}
                                    </td>
                                    <td>
                                        @if($device->assignedTest)
                                            <span class="td-badge run">{{ $device->assignedTest->name }}</span>
                                            <div class="td-muted">{{ $device->assigned_by_name }} · {{ $device->assigned_at?->format('d.m H:i') }}</div>
                                        @else
                                            <span class="td-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $device->registered_by_name ?? '—' }}<div class="td-muted td-num">{{ $device->created_at?->format('d.m.Y H:i') }}</div></td>
                                    <td class="td-num">{{ ($device->last_seen_at ?? $device->last_used_at)?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td>
                                        <div class="td-acts">
                                            @if($device->assigned_fan_testi_id)
                                                <form method="POST" action="{{ route('test-devices.release', $device) }}">
                                                    @csrf
                                                    <button type="submit" class="td-btn soft sm" title="Kompyuterni testdan bo'shatib, kutish ekraniga qaytarish">Testdan bo'shatish</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('test-devices.revoke', $device) }}"
                                                  onsubmit="return confirm('{{ addslashes($device->name) }} bekor qilinsinmi?')">
                                                @csrf
                                                <button type="submit" class="td-btn ghost sm">Bekor qilish</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="td-muted" style="margin:0">Hali birorta kompyuter ro'yxatdan o'tmagan.</p>
            @endforelse
        </div>

        @if($revoked->isNotEmpty())
            <div class="td-panel">
                <h2>Bekor qilinganlar ({{ $revoked->count() }})</h2>
                <div class="td-table-wrap">
                    <table class="td-table">
                        <thead><tr><th>Kompyuter</th><th>Sabab</th><th>Sana</th></tr></thead>
                        <tbody>
                        @foreach($revoked as $device)
                            <tr>
                                <td>{{ $device->room ? $device->room . '-xona · ' : '' }}{{ $device->name }}</td>
                                <td><span class="td-badge bad">{{ $device->revoked_reason }}</span></td>
                                <td class="td-num">{{ $device->revoked_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div></div>
</x-app-layout>
