{{--
    Bosqich holati badge'i — Dekan / Registrator / O'quv bo'limi ustunlari uchun.

    Parametrlar:
        $status — 'approved' | 'rejected' | 'pending' | null
        $userName — qaror qabul qilgan xodim nomi (ixtiyoriy)
        $decisionAt — qaror sanasi (Carbon, ixtiyoriy)
        $reason — rad etish sababi (ixtiyoriy)
        $extraNote — qo'shimcha izoh (ixtiyoriy, kichik harf bilan badge ostida)
--}}
@php
    $extraNote = $extraNote ?? null;
    $config = match($status ?? 'pending') {
        'approved' => [
            'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
            'label' => __('Tasdiqlagan'),
            'classes' => 'bg-green-100 text-green-800 border-green-200',
        ],
        'rejected' => [
            'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
            'label' => __('Rad etgan'),
            'classes' => 'bg-red-100 text-red-800 border-red-200',
        ],
        default => [
            'icon' => '<svg class="w-3.5 h-3.5 animate-pulse" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/></svg>',
            'label' => __('Kutyapti'),
            'classes' => 'bg-amber-50 text-amber-800 border-amber-200',
        ],
    };
@endphp

<div class="inline-flex flex-col items-center gap-0.5">
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $config['classes'] }}"
          @if(!empty($userName) || !empty($decisionAt) || !empty($reason))
              title="{{ trim(($userName ?? '') . ($decisionAt ? ' · ' . $decisionAt->format('d.m.Y H:i') : '') . ($reason ? ' · ' . $reason : '')) }}"
          @endif>
        {!! $config['icon'] !!}
        {{ $config['label'] }}
    </span>
    @if($extraNote)
        <span class="text-[10px] text-blue-700 italic">{{ $extraNote }}</span>
    @endif
</div>
