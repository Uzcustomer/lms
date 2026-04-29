{{-- Statistika kartochkalari (filter sifatida ham ishlaydi)
     Parametrlar:
     - $stats: ['pending', 'approved', 'rejected', 'all']
     - $statusKey: 'dean_status' yoki 'registrar_status'
     - $current: hozirgi filter qiymati
--}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <a href="?{{ $statusKey }}=pending"
       class="block p-4 rounded-xl border-2 transition
            {{ $current === 'pending' ? 'bg-yellow-50 border-yellow-400' : 'bg-white border-gray-200 hover:border-yellow-300' }}">
        <div class="text-xs font-medium text-gray-600 uppercase tracking-wide">Kutilmoqda</div>
        <div class="text-2xl font-bold text-yellow-700 mt-1">{{ $stats['pending'] }}</div>
    </a>
    <a href="?{{ $statusKey }}=approved"
       class="block p-4 rounded-xl border-2 transition
            {{ $current === 'approved' ? 'bg-emerald-50 border-emerald-400' : 'bg-white border-gray-200 hover:border-emerald-300' }}">
        <div class="text-xs font-medium text-gray-600 uppercase tracking-wide">Tasdiqlangan</div>
        <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $stats['approved'] }}</div>
    </a>
    <a href="?{{ $statusKey }}=rejected"
       class="block p-4 rounded-xl border-2 transition
            {{ $current === 'rejected' ? 'bg-red-50 border-red-400' : 'bg-white border-gray-200 hover:border-red-300' }}">
        <div class="text-xs font-medium text-gray-600 uppercase tracking-wide">Rad etilgan</div>
        <div class="text-2xl font-bold text-red-700 mt-1">{{ $stats['rejected'] }}</div>
    </a>
    <a href="?{{ $statusKey }}=all"
       class="block p-4 rounded-xl border-2 transition
            {{ $current === 'all' ? 'bg-blue-50 border-blue-400' : 'bg-white border-gray-200 hover:border-blue-300' }}">
        <div class="text-xs font-medium text-gray-600 uppercase tracking-wide">Jami</div>
        <div class="text-2xl font-bold text-blue-700 mt-1">{{ $stats['all'] }}</div>
    </a>
</div>
