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

    @php $activeTab = request('tab', 'list'); @endphp

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
            <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.staff-evaluation.generate-all-qr') }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                        Barchaga QR yaratish
                    </button>
                </form>
                @if($activeTab === 'qr')
                <form method="POST" action="{{ route('admin.staff-evaluation.delete-all-qr') }}"
                      onsubmit="return confirm('Barcha QR kodlar va ularga tegishli baholar o\'chiriladi. Davom etasizmi?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                        Hammasini o'chirish
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Tablar --}}
        <div class="flex gap-2 mb-6">
            <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'list'])) }}"
               class="px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ $activeTab === 'list' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Ro'yxat
            </a>
            <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'qr'])) }}"
               class="px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ $activeTab === 'qr' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                QR kodlar
            </a>
            <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'shablon'])) }}"
               class="px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ $activeTab === 'shablon' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Shablon
            </a>
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

        @elseif($activeTab === 'qr')
        {{-- ==================== QR KODLAR TABI ==================== --}}
        <div class="space-y-3">
            @forelse($teachers as $teacher)
            <a href="{{ route('admin.staff-evaluation.show', $teacher) }}"
               class="flex items-center gap-3 border rounded-lg p-3 hover:shadow-md transition-shadow group bg-white">
                <div class="flex-shrink-0 w-8 text-center text-sm font-bold text-gray-400">
                    {{ $teachers->firstItem() + $loop->index }}
                </div>
                <div class="flex-shrink-0 relative">
                    {!! QrCode::size(80)->errorCorrection('H')->margin(0)->generate(route('staff-evaluate.form', $teacher->eval_qr_token)) !!}
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="bg-white rounded-full" style="padding:3px;">
                            <img src="{{ asset('logo.png') }}" alt="Logo" class="rounded-full" style="width:28px;height:28px;">
                        </div>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-auto"
                         onclick="event.preventDefault(); window.location.href='{{ route('admin.staff-evaluation.download-qr', $teacher) }}'">
                        <span class="px-3 py-1 bg-white text-gray-800 rounded text-xs font-medium shadow">&#8681; Yuklab olish</span>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-gray-800 group-hover:text-blue-600 transition-colors">{{ $teacher->full_name }}</div>
                    @if($teacher->department)
                        <div class="text-sm text-gray-400">{{ $teacher->department }}</div>
                    @endif
                    @if($teacher->staff_position)
                        <div class="text-xs text-gray-400">{{ $teacher->staff_position }}</div>
                    @endif
                </div>
                <div class="flex-shrink-0 text-right">
                    @if($teacher->staff_evaluations_avg_rating)
                        @php
                            $avg = $teacher->staff_evaluations_avg_rating;
                            if ($avg >= 4) $rColor = 'text-green-600';
                            elseif ($avg >= 3) $rColor = 'text-yellow-600';
                            else $rColor = 'text-red-600';
                        @endphp
                        <div class="flex items-center gap-1 {{ $rColor }}">
                            <span>&#9733;</span>
                            <span class="text-lg font-bold">{{ number_format($avg, 1) }}</span>
                        </div>
                        <div class="text-xs text-gray-400">{{ $teacher->staff_evaluations_count }} ta baho</div>
                    @else
                        <span class="text-gray-300 text-sm">Baholar yo'q</span>
                    @endif
                </div>
            </a>
            @empty
            <div class="text-center text-gray-500 py-8">
                @if(request('search'))
                    "{{ request('search') }}" bo'yicha QR kodli xodim topilmadi.
                @else
                    QR kodli xodimlar yo'q. Avval QR kodlarni yarating.
                @endif
            </div>
            @endforelse
        </div>
        @elseif($activeTab === 'shablon')
        {{-- ==================== SHABLON TABI ==================== --}}
        <div class="space-y-6">
            @forelse($teachers as $teacher)
            @if($teacher->eval_qr_token)
            <div class="border rounded-lg overflow-hidden shadow-sm" id="card-{{ $teacher->id }}">
                {{-- Card template --}}
                <div style="width:600px; margin:0 auto; font-family:Arial,sans-serif;">
                    {{-- Header --}}
                    <div style="background:#1e3a8a; padding:14px 24px; display:flex; align-items:center; gap:14px;">
                        <img src="{{ asset('logo.png') }}" alt="Logo" style="width:48px;height:48px;border-radius:50%;">
                        <div>
                            <div style="color:white; font-size:16px; font-weight:700; line-height:1.3;">Toshkent davlat tibbiyot universiteti</div>
                            <div style="color:#93c5fd; font-size:13px; font-weight:500;">Termiz filiali</div>
                        </div>
                    </div>
                    {{-- Body --}}
                    <div style="background:#2563eb; padding:20px 24px; display:flex; align-items:center; gap:0;">
                        {{-- Chap tomon — O'zbekcha --}}
                        <div style="flex:1; text-align:center; color:white; padding-right:16px;">
                            <div style="font-size:14px; line-height:1.5;">
                                QR kod orqali<br>
                                xodimning xizmat<br>
                                ko'rsatish sifatini<br>
                                baholang
                            </div>
                        </div>
                        {{-- O'rta — QR kod --}}
                        <div style="flex-shrink:0; background:white; padding:8px; border-radius:8px;">
                            {!! QrCode::size(140)->errorCorrection('H')->margin(0)->generate(route('staff-evaluate.form', $teacher->eval_qr_token)) !!}
                        </div>
                        {{-- O'ng tomon — Ruscha --}}
                        <div style="flex:1; text-align:center; color:white; padding-left:16px;">
                            <div style="font-size:14px; line-height:1.5;">
                                Оцените качество<br>
                                обслуживания<br>
                                сотрудника с<br>
                                помощью QR кода
                            </div>
                        </div>
                    </div>
                    {{-- Footer — xodim ismi --}}
                    <div style="background:#1e3a8a; padding:8px 24px; text-align:center;">
                        <span style="color:white; font-size:13px; font-weight:600;">{{ $teacher->full_name }}</span>
                        @if($teacher->staff_position)
                            <span style="color:#93c5fd; font-size:12px;"> — {{ $teacher->staff_position }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
            @empty
            <div class="text-center text-gray-500 py-8">
                QR kodli xodimlar yo'q. Avval QR kodlarni yarating.
            </div>
            @endforelse
        </div>
        @endif

        @if($activeTab !== 'shablon')
        <div class="mt-4">
            {{ $teachers->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
