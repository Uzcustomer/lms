<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Registrator Face ID tekshiruvi
    </h2>
</x-slot>

<div class="container mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">🆔 Test markaziga kirish — webcam tekshiruv</h1>
            <p class="text-sm text-gray-500">Talabaning yuzini bu yerda webcam orqali tekshirib, test markazidagi muammolarning oldini oling.</p>
        </div>
        @if($canEditSettings)
            <button type="button" onclick="fcSettings(true)" title="Tekshiruv chegaralari"
                    style="padding:8px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;color:#6b7280;cursor:pointer;line-height:0;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>
        @endif
    </div>

    @if(session('face_check_settings_saved'))
        <div style="margin-bottom:16px;padding:8px 16px;border:1px solid #bbf7d0;border-radius:6px;background:#f0fdf4;color:#166534;font-size:14px;">{{ session('face_check_settings_saved') }}</div>
    @endif

    @if($canEditSettings)
        {{-- Tekshiruv chegaralari (faqat superadmin/admin — registrator ko'rmaydi) --}}
        @php($fcErr = $errors->has('threshold_hemis') || $errors->has('threshold_mark'))
        <div id="fcSettingsModal" onclick="if (event.target === this) fcSettings(false)"
             style="display:{{ $fcErr ? 'flex' : 'none' }};position:fixed;inset:0;z-index:50;align-items:center;justify-content:center;background:rgba(0,0,0,.4);padding:0 16px;">
            <form method="POST" action="{{ route('admin.registrator.face-check.settings') }}"
                  style="width:100%;max-width:440px;background:#fff;border-radius:12px;box-shadow:0 20px 40px rgba(0,0,0,.2);padding:20px;">
                @csrf
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <h3 style="font-size:16px;font-weight:600;color:#1f2937;">Tekshiruv chegaralari</h3>
                    <button type="button" onclick="fcSettings(false)" style="border:none;background:none;font-size:22px;line-height:1;color:#9ca3af;cursor:pointer;">&times;</button>
                </div>
                <p style="font-size:12px;color:#6b7280;margin-bottom:16px;">
                    Kamera rasmi ikkala rasm bilan solishtiriladi; ikkalasi ham chegaradan o'tsa — tasdiqlanadi.
                    Chegara pasaysa, boshqa odamning o'tib ketish xavfi oshadi.
                </p>

                <label for="threshold_hemis" style="display:block;font-size:14px;font-weight:500;color:#374151;">HEMIS rasmi bilan o'xshashlik (%)</label>
                <input id="threshold_hemis" name="threshold_hemis" type="number" step="0.5" min="50" max="99" required
                       value="{{ old('threshold_hemis', $thresholds['hemis']) }}"
                       style="margin-top:4px;width:100%;border:1px solid #d1d5db;border-radius:6px;padding:6px 12px;font-size:14px;">
                <p style="font-size:12px;color:#9ca3af;margin:4px 0 12px;">Standart: 71. HEMIS rasmi odatda eski (1-kurs) — shuning uchun pastroq.</p>
                @error('threshold_hemis')<p style="font-size:12px;color:#dc2626;margin:-8px 0 12px;">{{ $message }}</p>@enderror

                <label for="threshold_mark" style="display:block;font-size:14px;font-weight:500;color:#374151;">Mark (tasdiqlangan) rasmi bilan o'xshashlik (%)</label>
                <input id="threshold_mark" name="threshold_mark" type="number" step="0.5" min="50" max="99" required
                       value="{{ old('threshold_mark', $thresholds['mark']) }}"
                       style="margin-top:4px;width:100%;border:1px solid #d1d5db;border-radius:6px;padding:6px 12px;font-size:14px;">
                <p style="font-size:12px;color:#9ca3af;margin:4px 0 12px;">Standart: 82. Yangi rasm, yorug'lik nazorat ostida.</p>
                @error('threshold_mark')<p style="font-size:12px;color:#dc2626;margin:-8px 0 12px;">{{ $message }}</p>@enderror

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
                    <button type="button" onclick="fcSettings(false)" style="padding:6px 12px;font-size:14px;border:none;border-radius:6px;background:#f3f4f6;color:#374151;cursor:pointer;">Bekor qilish</button>
                    <button type="submit" style="padding:6px 16px;font-size:14px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;">Saqlash</button>
                </div>
            </form>
        </div>
        <script>
            function fcSettings(open) {
                document.getElementById('fcSettingsModal').style.display = open ? 'flex' : 'none';
            }
        </script>
    @endif

    <form method="GET" class="bg-white rounded-lg border p-4 mb-4 flex gap-3 items-end">
        <div class="flex-1">
            <label class="block text-xs text-gray-500 mb-1">Talaba (ism, ID raqam yoki passport)</label>
            <input type="text" name="q" value="{{ $query }}"
                   class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                   placeholder="Familiya yoki ID..." />
        </div>
        <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
            Qidirish
        </button>
    </form>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-2">F.I.O</th>
                    <th class="text-left px-4 py-2">ID raqam</th>
                    <th class="text-left px-4 py-2">Guruh</th>
                    <th class="text-left px-4 py-2">Holati</th>
                    <th class="text-left px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                @php($isApproved = isset($approvedIds[(string) $student->student_id_number]))
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $student->full_name }}</td>
                    <td class="px-4 py-2 font-mono">{{ $student->student_id_number }}</td>
                    <td class="px-4 py-2">{{ $student->group_name }}</td>
                    <td class="px-4 py-2">
                        @if($isApproved)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                Tasdiqlandi
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                Tekshirilmagan
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('admin.registrator.face-check.show', $student->student_id_number) }}"
                           class="px-3 py-1 {{ $isApproved ? 'bg-gray-500 hover:bg-gray-600' : 'bg-emerald-600 hover:bg-emerald-700' }} text-white rounded text-xs">
                            {{ $isApproved ? 'Qayta tekshirish' : 'Tekshirish' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Topilmadi</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $students->links() }}</div>
</div>
</x-app-layout>
