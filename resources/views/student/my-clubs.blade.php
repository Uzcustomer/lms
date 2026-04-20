<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Mening to'garaklarim
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3 py-4">

        @if($myMemberships->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($myMemberships as $m)
                    <div class="bg-white border border-gray-200 rounded-xl p-4 {{ $m->status === 'rejected' ? 'border-red-200' : ($m->status === 'approved' ? 'border-green-200' : 'border-yellow-200') }}">
                        <div class="flex items-start justify-between mb-2">
                            <div class="font-bold text-sm text-gray-800">{{ $m->club_name }}</div>
                            @if($m->status === 'pending')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-yellow-100 text-yellow-700 flex-shrink-0">Kutilmoqda</span>
                            @elseif($m->status === 'approved')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-100 text-green-700 flex-shrink-0">Tasdiqlangan</span>
                            @elseif($m->status === 'rejected')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-red-100 text-red-700 flex-shrink-0">Rad etilgan</span>
                            @endif
                        </div>
                        <div class="flex flex-col gap-1 text-xs text-gray-500">
                            @if($m->kafedra_name)
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                                    <span>{{ $m->kafedra_name }}</span>
                                </div>
                            @endif
                            @if($m->club_place)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 0115 0z"/></svg>
                                    <span>{{ $m->club_place }}</span>
                                </div>
                            @endif
                            @if($m->club_day)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                    <span>{{ $m->club_day }}</span>
                                </div>
                            @endif
                            @if($m->club_time)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>{{ $m->club_time }}</span>
                                </div>
                            @endif
                        </div>
                        @if($m->status === 'rejected' && $m->reject_reason)
                            <div class="mt-2 p-2 bg-red-50 rounded-lg text-xs text-red-600">
                                <span class="font-semibold">Rad etish sababi:</span> {{ $m->reject_reason }}
                            </div>
                        @endif
                        <div class="text-[11px] text-gray-400 mt-2">Ariza yuborilgan: {{ $m->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                </svg>
                <p class="mt-4 text-sm text-gray-500">Siz hali hech qanday to'garakka a'zo bo'lmagansiz</p>
                <a href="{{ route('student.clubs') }}" class="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-700">To'garaklarga o'tish</a>
            </div>
        @endif
    </div>
</x-student-app-layout>
