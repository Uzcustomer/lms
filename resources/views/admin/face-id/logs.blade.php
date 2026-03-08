@extends('layouts.admin')

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
