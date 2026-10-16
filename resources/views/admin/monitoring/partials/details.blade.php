@php
    $labels = [
        'oquv_yili' => "O'quv yili", 'semestr' => 'Semestr', 'sababsiz_soat' => 'Sababsiz soat (HEMIS)',
        'tibbiy_ayirilgan_soat' => 'Tibbiy arizalar bo\'yicha ayirilgan', 'hisoblangan_soat' => 'Hisoblangan soat',
        'chegara' => 'Chegara', 'kutilgan_harakat' => 'Kutilgan harakat', 'guruh' => 'Guruh', 'fan' => 'Fan',
        'nazorat_sanasi' => 'Nazorat sanasi', 'muddat' => 'Muddat', 'talabalar' => 'Faol talabalar',
        'kiritilmagan' => 'Baho kiritilmagan', 'kechikib_kiritilgan' => 'Kechikib kiritilgan',
        'kontrakt_turi' => 'Kontrakt turi', 'tolov_foizi' => "To'lov, %", 'talab_foizi' => 'Talab, %',
        'qabul_buyrugi' => "Qabul buyrug'i", 'kontingentda' => 'Kontingentda', 'signallar' => 'Signallar',
        'moslik' => 'Talabaga moslik', 'talab' => 'Talab', 'buyruq_raqami' => 'Buyruq raqami',
        'buyruq_sanasi' => 'Buyruq sanasi', 'turi' => 'Turi', 'xabar_berilmagan' => 'Xabar berilmagan',
        'kechikib_xabar_berilgan' => 'Kechikib xabar berilgan', 'turar_joy' => 'Turar joy', 'kelgan_sana' => 'Kelgan sana',
        'yetishmaydi' => 'Yetishmaydi',
    ];
@endphp
<dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-1 text-sm">
    @foreach(($details ?? []) as $key => $value)
        <dt class="text-gray-500">{{ $labels[$key] ?? $key }}</dt>
        <dd class="sm:col-span-2 text-gray-900">
            @if(is_array($value))
                @if($value === []) — @else
                    <ul class="list-disc ml-4">@foreach($value as $v)<li>{{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v }}</li>@endforeach</ul>
                @endif
            @elseif(is_bool($value))
                {{ $value ? 'ha' : "yo'q" }}
            @else
                {{ $value ?? '—' }}
            @endif
        </dd>
    @endforeach
</dl>
