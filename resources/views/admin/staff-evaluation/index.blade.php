<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Xodimlarni baholash
        </h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Xodimlar ro'yxati</h3>
            <form method="POST" action="{{ route('admin.staff-evaluation.generate-all-qr') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                    Barchaga QR kod yaratish
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Xodim</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">O'rtacha baho</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Baholar soni</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">QR kod</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Amallar</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.staff-evaluation.show', $user) }}"
                               class="text-blue-600 hover:text-blue-800 font-medium">
                                {{ $user->name }}
                            </a>
                            <div class="text-xs text-gray-400">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($hasTable && $user->staff_evaluations_avg_rating)
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-yellow-500">&#9733;</span>
                                    <span class="font-semibold text-gray-800">{{ number_format($user->staff_evaluations_avg_rating, 1) }}</span>
                                </div>
                            @else
                                <span class="text-gray-400 text-sm">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">
                            {{ $hasTable ? $user->staff_evaluations_count : 0 }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($user->eval_qr_token)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Mavjud
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    Yo'q
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                @if(!$user->eval_qr_token)
                                <form method="POST" action="{{ route('admin.staff-evaluation.generate-qr', $user) }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                        QR yaratish
                                    </button>
                                </form>
                                @else
                                <a href="{{ route('admin.staff-evaluation.download-qr', $user) }}"
                                   class="px-3 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700 inline-block">
                                    Yuklab olish
                                </a>
                                @endif
                                <a href="{{ route('admin.staff-evaluation.show', $user) }}"
                                   class="px-3 py-1 bg-gray-600 text-white rounded text-xs hover:bg-gray-700 inline-block">
                                    Batafsil
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Xodimlar topilmadi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
