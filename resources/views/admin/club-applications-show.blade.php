<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ariza: {{ $application->club_name }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
            @endif

            <a href="{{ route('admin.club-applications.index') }}" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                Orqaga
            </a>

            {{-- Talaba ma'lumotlari --}}
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 mb-4">
                <div class="px-5 py-3 border-b border-gray-200 bg-gray-50 rounded-t-xl">
                    <h3 class="font-semibold text-sm text-gray-700">Talaba ma'lumotlari</h3>
                </div>
                <div class="px-5 py-4 grid grid-cols-2 gap-y-3 gap-x-6 text-sm">
                    <div>
                        <div class="text-xs text-gray-400">F.I.O</div>
                        <div class="font-semibold text-gray-800">{{ $application->student_name }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">HEMIS ID</div>
                        <div class="font-semibold text-gray-800">{{ $application->student_hemis_id }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Guruh</div>
                        <div class="font-semibold text-gray-800">{{ $application->group_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Ariza sanasi</div>
                        <div class="font-semibold text-gray-800">{{ $application->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            </div>

            {{-- To'garak ma'lumotlari --}}
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 mb-4">
                <div class="px-5 py-3 border-b border-gray-200 bg-gray-50 rounded-t-xl">
                    <h3 class="font-semibold text-sm text-gray-700">To'garak ma'lumotlari</h3>
                </div>
                <div class="px-5 py-4 grid grid-cols-2 gap-y-3 gap-x-6 text-sm">
                    <div class="col-span-2">
                        <div class="text-xs text-gray-400">To'garak nomi</div>
                        <div class="font-semibold text-gray-800">{{ $application->club_name }}</div>
                    </div>
                    <div class="col-span-2">
                        <div class="text-xs text-gray-400">Kafedra</div>
                        <div class="font-semibold text-gray-800">{{ $application->kafedra_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Mashg'ulot joyi</div>
                        <div class="font-semibold text-gray-800">{{ $application->club_place ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">O'tkaziladigan kuni</div>
                        <div class="font-semibold text-gray-800">{{ $application->club_day ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Soati</div>
                        <div class="font-semibold text-gray-800">{{ $application->club_time ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Status</div>
                        <div>
                            @if($application->status === 'pending')
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">Kutilmoqda</span>
                            @elseif($application->status === 'approved')
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Tasdiqlangan</span>
                            @else
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Rad etilgan</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rad etilgan bo'lsa sababi --}}
            @if($application->status === 'rejected' && $application->reject_reason)
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 text-sm text-red-700">
                    <span class="font-semibold">Rad etish sababi:</span> {{ $application->reject_reason }}
                </div>
            @endif

            {{-- Action buttons --}}
            @if($application->status === 'pending')
                <div class="bg-white shadow-sm rounded-xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-sm text-gray-700 mb-4">Qaror qabul qilish</h3>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <form method="POST" action="{{ route('admin.club-applications.approve', $application) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-xl hover:bg-green-700 transition">To'garakka biriktirildi</button>
                        </form>
                        <form method="POST" action="{{ route('admin.club-applications.reject', $application) }}" class="flex-1" x-data="{ showReason: false }">
                            @csrf
                            <button type="button" @click="showReason = !showReason" class="w-full px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-700 transition">Rad etish</button>
                            <div x-show="showReason" x-cloak class="mt-3">
                                <textarea name="reject_reason" rows="2" placeholder="Rad etish sababini yozing..." class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-red-200 focus:border-red-400"></textarea>
                                <button type="submit" class="mt-2 w-full px-4 py-2 bg-red-500 text-white text-sm font-semibold rounded-xl hover:bg-red-600 transition">Rad etishni tasdiqlash</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
