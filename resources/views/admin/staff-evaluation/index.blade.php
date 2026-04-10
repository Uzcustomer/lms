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
        {{-- Qidiruv va amallar --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <form method="GET" action="{{ route('admin.staff-evaluation.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ request('tab', 'list') }}">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Ism, familya, kafedra..."
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 w-64">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                    Qidirish
                </button>
                @if(request('search'))
                    <a href="{{ route('admin.staff-evaluation.index', ['tab' => request('tab', 'list')]) }}" class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm font-medium">
                        Tozalash
                    </a>
                @endif
            </form>
            <form method="POST" action="{{ route('admin.staff-evaluation.generate-all-qr') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                    Barchaga QR yaratish
                </button>
            </form>
        </div>

        {{-- Tablar --}}
        @php $activeTab = request('tab', 'list'); @endphp
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex gap-4 -mb-px">
                <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'list'])) }}"
                   class="pb-3 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'list' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Ro'yxat
                </a>
                <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'qr'])) }}"
                   class="pb-3 px-1 text-sm font-medium border-b-2 {{ $activeTab === 'qr' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    QR kodlar
                </a>
            </nav>
        </div>

        @if($activeTab === 'list')
        {{-- ==================== RO'YXAT TABI ==================== --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Xodim</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kafedra</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">O'rtacha baho</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Baholar soni</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">QR</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Amallar</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($teachers as $teacher)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $teachers->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.staff-evaluation.show', $teacher) }}"
                               class="text-blue-600 hover:text-blue-800 font-medium">
                                {{ $teacher->full_name }}
                            </a>
                            @if($teacher->staff_position)
                                <div class="text-xs text-gray-400">{{ $teacher->staff_position }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $teacher->department ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($teacher->staff_evaluations_avg_rating)
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-yellow-500">&#9733;</span>
                                    <span class="font-semibold text-gray-800">{{ number_format($teacher->staff_evaluations_avg_rating, 1) }}</span>
                                </div>
                            @else
                                <span class="text-gray-400 text-sm">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">
                            {{ $teacher->staff_evaluations_count }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($teacher->eval_qr_token)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">&#10003;</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                @if(!$teacher->eval_qr_token)
                                <form method="POST" action="{{ route('admin.staff-evaluation.generate-qr', $teacher) }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                        QR yaratish
                                    </button>
                                </form>
                                @else
                                <a href="{{ route('admin.staff-evaluation.download-qr', $teacher) }}"
                                   class="px-3 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700 inline-block">
                                    Yuklab olish
                                </a>
                                @endif
                                <a href="{{ route('admin.staff-evaluation.show', $teacher) }}"
                                   class="px-3 py-1 bg-gray-600 text-white rounded text-xs hover:bg-gray-700 inline-block">
                                    Batafsil
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            @if(request('search'))
                                "{{ request('search') }}" bo'yicha xodim topilmadi.
                            @else
                                Xodimlar topilmadi.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @else
        {{-- ==================== QR KODLAR TABI ==================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @forelse($teachers as $teacher)
            <div class="border rounded-lg p-4 text-center hover:shadow-md transition-shadow group relative">
                <div class="font-semibold text-gray-800 mb-1">{{ $teacher->full_name }}</div>
                @if($teacher->department)
                    <div class="text-xs text-gray-400 mb-3">{{ $teacher->department }}</div>
                @endif

                @if($teacher->eval_qr_token)
                    <div class="relative flex justify-center mb-3">
                        <div>
                            {!! QrCode::size(160)->margin(1)->generate(route('staff-evaluate.form', $teacher->eval_qr_token)) !!}
                        </div>
                        <a href="{{ route('admin.staff-evaluation.download-qr', $teacher) }}"
                           class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="px-4 py-2 bg-white text-gray-800 rounded-lg text-sm font-medium shadow">
                                &#8681; Yuklab olish
                            </span>
                        </a>
                    </div>
                    <div class="flex items-center justify-center gap-2">
                        @if($teacher->staff_evaluations_avg_rating)
                            <span class="text-yellow-500">&#9733;</span>
                            <span class="text-sm font-semibold">{{ number_format($teacher->staff_evaluations_avg_rating, 1) }}</span>
                            <span class="text-xs text-gray-400">({{ $teacher->staff_evaluations_count }})</span>
                        @endif
                    </div>
                @else
                    <div class="py-6 text-gray-300">
                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                        </svg>
                    </div>
                    <form method="POST" action="{{ route('admin.staff-evaluation.generate-qr', $teacher) }}">
                        @csrf
                        <button type="submit" class="mt-2 px-3 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                            QR yaratish
                        </button>
                    </form>
                @endif
            </div>
            @empty
            <div class="col-span-full text-center text-gray-500 py-8">
                @if(request('search'))
                    "{{ request('search') }}" bo'yicha xodim topilmadi.
                @else
                    Xodimlar topilmadi.
                @endif
            </div>
            @endforelse
        </div>
        @endif

        <div class="mt-4">
            {{ $teachers->links() }}
        </div>
    </div>
</x-app-layout>
