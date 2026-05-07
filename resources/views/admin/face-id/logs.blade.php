@extends('layouts.app')

@section('title', 'Face ID Loglar')

@section('content')
<div class="container mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">📋 Face ID Loglar</h1>
            <p class="text-sm text-gray-500">Bugun: {{ $todaySuccess }}/{{ $todayTotal }} muvaffaqiyatli</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.face-id.settings') }}"
               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">⚙️ Sozlamalar</a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <!-- Umumiy statistika -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
        <div class="bg-white border rounded-lg p-3">
            <div class="text-[11px] uppercase font-bold text-gray-400 tracking-wide">Jami</div>
            <div class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
            <div class="text-[11px] uppercase font-bold text-green-600 tracking-wide">✅ Muvaffaqiyatli</div>
            <div class="text-2xl font-bold text-green-700 mt-1">{{ number_format($stats['success']) }}</div>
            <div class="text-[11px] text-green-600/70">{{ $stats['total'] ? round($stats['success'] / $stats['total'] * 100, 1) : 0 }}%</div>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
            <div class="text-[11px] uppercase font-bold text-red-600 tracking-wide">❌ Failed</div>
            <div class="text-2xl font-bold text-red-700 mt-1">{{ number_format($stats['failed']) }}</div>
            <div class="text-[11px] text-red-600/70">{{ $stats['total'] ? round($stats['failed'] / $stats['total'] * 100, 1) : 0 }}%</div>
        </div>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
            <div class="text-[11px] uppercase font-bold text-yellow-700 tracking-wide">👁️ Liveness</div>
            <div class="text-2xl font-bold text-yellow-700 mt-1">{{ number_format($stats['liveness_failed']) }}</div>
        </div>
        <div class="bg-gray-50 border rounded-lg p-3">
            <div class="text-[11px] uppercase font-bold text-gray-500 tracking-wide">🔍 Topilmadi</div>
            <div class="text-2xl font-bold text-gray-700 mt-1">{{ number_format($stats['not_found']) }}</div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <div class="text-[11px] uppercase font-bold text-blue-600 tracking-wide">O'rt. yaqinlik</div>
            <div class="text-2xl font-bold text-blue-700 mt-1">
                {{ $stats['avg_confidence'] !== null ? round($stats['avg_confidence'] * 100, 1) . '%' : '—' }}
            </div>
        </div>
    </div>

    <!-- Confidence oraliqlari -->
    <div class="bg-white border rounded-lg p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-semibold text-gray-700">📊 Yaqinlik (confidence) oraliqlari</div>
            <div class="text-xs text-gray-400">Faqat confidence yozilgan loglar</div>
        </div>
        @php
            $rangeTotal = array_sum(array_column($confidenceRanges, 'count'));
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($confidenceRanges as $r)
            @php $pct = $rangeTotal ? round($r['count'] / $rangeTotal * 100, 1) : 0; @endphp
            <div class="border rounded-lg p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $r['color'] }}">{{ $r['label'] }}</span>
                    <span class="text-xs text-gray-400">{{ $pct }}%</span>
                </div>
                <div class="text-xl font-bold text-gray-800">{{ number_format($r['count']) }}</div>
                <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="h-full bg-blue-500" style="width: {{ $pct }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Filter -->
    <form method="GET" class="bg-white rounded-lg border p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Natija</label>
            <select name="result" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
                <option value="">Barchasi</option>
                <option value="success"         {{ request('result') === 'success'         ? 'selected' : '' }}>✅ Muvaffaqiyatli</option>
                <option value="failed"          {{ request('result') === 'failed'          ? 'selected' : '' }}>❌ Muvaffaqiyatsiz</option>
                <option value="liveness_failed" {{ request('result') === 'liveness_failed' ? 'selected' : '' }}>👁️ Liveness muvaffaqiyatsiz</option>
                <option value="not_found"       {{ request('result') === 'not_found'       ? 'selected' : '' }}>🔍 Topilmadi</option>
                <option value="disabled"        {{ request('result') === 'disabled'        ? 'selected' : '' }}>🚫 O'chirilgan</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Talaba ID</label>
            <input type="text" name="student_id_number" value="{{ request('student_id_number') }}"
                   placeholder="ID raqam" class="border border-gray-300 rounded px-2 py-1.5 text-sm w-44">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Sana</label>
            <input type="date" name="date" value="{{ request('date') }}"
                   class="border border-gray-300 rounded px-2 py-1.5 text-sm">
        </div>
        <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg">Filter</button>
        <a href="{{ route('admin.face-id.logs') }}" class="px-4 py-1.5 bg-gray-100 text-gray-600 text-sm rounded-lg">Tozalash</a>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-lg border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vaqt</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Natija</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Yaqinlik</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sabab</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Snapshot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ $log->created_at->format('d.m.Y H:i:s') }}
                        </td>
                        <td class="px-4 py-3">
                            @if($log->student)
                                <div class="font-medium text-gray-800 text-xs">{{ $log->student->full_name }}</div>
                            @endif
                            <div class="text-gray-400 text-xs font-mono">{{ $log->student_id_number }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $colors = [
                                    'success'         => 'bg-green-100 text-green-700',
                                    'failed'          => 'bg-red-100 text-red-700',
                                    'liveness_failed' => 'bg-yellow-100 text-yellow-700',
                                    'not_found'       => 'bg-gray-100 text-gray-600',
                                    'disabled'        => 'bg-gray-100 text-gray-500',
                                ];
                                $color = $colors[$log->result] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                {{ $log->result_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if($log->confidence !== null)
                                <span class="{{ $log->confidence >= 0.6 ? 'text-green-600' : 'text-red-600' }} font-mono">
                                    {{ round($log->confidence * 100, 1) }}%
                                </span>
                                @if($log->distance !== null)
                                <div class="text-gray-400 text-xs">d={{ round($log->distance, 3) }}</div>
                                @endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                            {{ $log->failure_reason ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $log->ip_address }}</td>
                        <td class="px-4 py-3">
                            @if($log->snapshot)
                                <a href="{{ route('admin.face-id.logs.snapshot', $log->id) }}" target="_blank"
                                   class="text-blue-500 hover:text-blue-700 text-xs underline">Ko'rish</a>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Loglar topilmadi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-4 py-3 border-t">{{ $logs->links() }}</div>
        @endif
    </div>

</div>
@endsection
