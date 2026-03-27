<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">To'garak arizalari</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @php
                $pending = $applications->where('status', 'pending');
                $approved = $applications->where('status', 'approved');
                $rejected = $applications->where('status', 'rejected');
            @endphp

            {{-- Stats row --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-700">{{ $pending->count() }}</div>
                    <div class="text-sm text-yellow-600">Kutilmoqda</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-green-700">{{ $approved->count() }}</div>
                    <div class="text-sm text-green-600">Tasdiqlangan</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-red-700">{{ $rejected->count() }}</div>
                    <div class="text-sm text-red-600">Rad etilgan</div>
                </div>
            </div>

            {{-- Applications grid --}}
            @if($applications->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($applications as $app)
                        <div class="bg-white border rounded-xl p-4 {{ $app->status === 'pending' ? 'border-yellow-300' : ($app->status === 'approved' ? 'border-green-300' : 'border-red-300') }}">
                            <div class="flex items-start justify-between mb-2">
                                <div class="font-bold text-sm text-gray-800">{{ $app->student_name }}</div>
                                @if($app->status === 'pending')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-yellow-100 text-yellow-700 flex-shrink-0">Kutilmoqda</span>
                                @elseif($app->status === 'approved')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700 flex-shrink-0">Tasdiqlangan</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-700 flex-shrink-0">Rad etilgan</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mb-1">{{ $app->group_name }}</div>
                            <div class="text-xs font-semibold text-gray-700 mb-1">{{ $app->club_name }}</div>
                            <div class="text-[11px] text-gray-400 mb-3">{{ $app->created_at->format('d.m.Y H:i') }}</div>
                            <a href="{{ route('admin.club-applications.show', $app) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Ko'rish
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400 text-sm">
                    Arizalar mavjud emas
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
