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

            {{-- Stats --}}
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

            {{-- Pending --}}
            @if($pending->count() > 0)
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 mb-6">
                <div class="px-4 py-3 border-b border-gray-200 bg-yellow-50 rounded-t-xl">
                    <h3 class="font-semibold text-sm text-yellow-800">Kutilayotgan arizalar ({{ $pending->count() }})</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($pending as $app)
                    <div class="px-4 py-3 flex items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-gray-800">{{ $app->student_name }}</div>
                            <div class="text-xs text-gray-500">{{ $app->group_name }} &middot; {{ $app->club_name }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $app->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <form method="POST" action="{{ route('admin.club-applications.approve', $app) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700 transition">Tasdiqlash</button>
                            </form>
                            <form method="POST" action="{{ route('admin.club-applications.reject', $app) }}" x-data="{ open: false }">
                                @csrf
                                <button type="button" @click="open = !open" class="px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition">Rad etish</button>
                                <div x-show="open" x-cloak class="mt-2">
                                    <input type="text" name="reject_reason" placeholder="Sabab (ixtiyoriy)" class="w-full text-xs border border-gray-300 rounded-lg px-2 py-1 mb-1">
                                    <button type="submit" class="text-xs text-red-600 font-semibold">Tasdiqlash</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- All applications table --}}
            <div class="bg-white shadow-sm rounded-xl border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="font-semibold text-sm text-gray-800">Barcha arizalar ({{ $applications->count() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left">
                                <th class="px-4 py-2 font-semibold text-gray-600">#</th>
                                <th class="px-4 py-2 font-semibold text-gray-600">Talaba</th>
                                <th class="px-4 py-2 font-semibold text-gray-600">Guruh</th>
                                <th class="px-4 py-2 font-semibold text-gray-600">To'garak</th>
                                <th class="px-4 py-2 font-semibold text-gray-600">Sana</th>
                                <th class="px-4 py-2 font-semibold text-gray-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($applications as $i => $app)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $app->student_name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $app->group_name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $app->club_name }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $app->created_at->format('d.m.Y') }}</td>
                                <td class="px-4 py-2">
                                    @if($app->status === 'pending')
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">Kutilmoqda</span>
                                    @elseif($app->status === 'approved')
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Tasdiqlangan</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700" title="{{ $app->reject_reason }}">Rad etilgan</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Arizalar mavjud emas</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
