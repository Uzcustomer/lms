@php
    /** @var \App\Models\MonitoringIstisno $istisno */
    $cls = match (true) {
        $istisno->warning_only && $istisno->isOpen() => 'bg-yellow-100 text-yellow-800',
        $istisno->isOverdue() => 'bg-red-100 text-red-800',
        $istisno->status === 'javob_berildi' => 'bg-blue-100 text-blue-800',
        $istisno->status === 'asosli_istisno' => 'bg-purple-100 text-purple-800',
        $istisno->isOpen() => 'bg-orange-100 text-orange-800',
        default => 'bg-green-100 text-green-800',
    };
@endphp
<span class="inline-block px-2 py-0.5 text-xs font-medium rounded {{ $cls }}">
    {{ $istisno->statusLabel() }}@if($istisno->warning_only && $istisno->isOpen()) · ogohlantirish @elseif($istisno->isOverdue()) · muddati o'tgan @endif
</span>
