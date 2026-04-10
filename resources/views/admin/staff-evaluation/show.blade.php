<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $teacher->full_name }} — Baholar
        </h2>
    </x-slot>

    <div class="mb-4">
        <a href="{{ route('admin.staff-evaluation.index') }}"
           class="text-blue-600 hover:text-blue-800 text-sm">
            &larr; Orqaga
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    {{-- Umumiy statistika --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white shadow rounded-lg p-5 text-center">
            <div class="text-3xl font-bold text-yellow-500">
                @if($avgRating)
                    &#9733; {{ number_format($avgRating, 1) }}
                @else
                    —
                @endif
            </div>
            <div class="text-sm text-gray-500 mt-1">O'rtacha baho</div>
        </div>
        <div class="bg-white shadow rounded-lg p-5 text-center">
            <div class="text-3xl font-bold text-gray-800">{{ $totalCount }}</div>
            <div class="text-sm text-gray-500 mt-1">Jami baholar</div>
        </div>
        <div class="bg-white shadow rounded-lg p-5">
            <div class="text-sm text-gray-500 mb-2 text-center">Taqsimot</div>
            @foreach($ratingDistribution as $star => $count)
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs w-8 text-right">{{ $star }} &#9733;</span>
                <div class="flex-1 bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full {{ $star >= 4 ? 'bg-green-500' : ($star == 3 ? 'bg-yellow-500' : 'bg-red-500') }}"
                         style="width: {{ $totalCount > 0 ? ($count / $totalCount * 100) : 0 }}%"></div>
                </div>
                <span class="text-xs w-6 text-gray-500">{{ $count }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- QR kod --}}
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        @if($teacher->eval_qr_token)
        <div class="flex flex-col sm:flex-row items-center gap-6">
            <div class="flex-shrink-0 bg-white p-3 border rounded-lg">
                {!! QrCode::size(200)->margin(1)->generate(route('staff-evaluate.form', $teacher->eval_qr_token)) !!}
            </div>
            <div class="flex-1 text-center sm:text-left">
                <h3 class="font-semibold text-gray-800 mb-2">QR kod</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Havola: <code class="bg-gray-100 px-2 py-0.5 rounded text-xs break-all">{{ route('staff-evaluate.form', $teacher->eval_qr_token) }}</code>
                </p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.staff-evaluation.download-qr', $teacher) }}"
                       class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 inline-block">
                        SVG yuklab olish
                    </a>
                    <form method="POST" action="{{ route('admin.staff-evaluation.regenerate-qr', $teacher) }}"
                          onsubmit="return confirm('QR kod qayta yaratiladi va barcha eski baholar o\'chiriladi. Davom etasizmi?')">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600">
                            Qayta yaratish
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.staff-evaluation.delete-qr', $teacher) }}"
                          onsubmit="return confirm('QR kod va barcha baholar butunlay o\'chiriladi. Davom etasizmi?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">
                            O'chirish
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @else
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-800">QR kod</h3>
                <p class="text-sm text-gray-400 mt-1">QR kod hali yaratilmagan.</p>
            </div>
            <form method="POST" action="{{ route('admin.staff-evaluation.generate-qr', $teacher) }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                    QR yaratish
                </button>
            </form>
        </div>
        @endif
    </div>

    {{-- Yulduz filtrlari --}}
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-600 mr-1">Filtr:</span>
            <a href="{{ route('admin.staff-evaluation.show', $teacher) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ !request('rating') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Barchasi ({{ $totalCount }})
            </a>
            @foreach($ratingDistribution as $star => $count)
                @php
                    if ($star >= 4) { $btnActive = 'bg-green-600 text-white'; $btnInactive = 'bg-green-50 text-green-700 hover:bg-green-100 border border-green-200'; }
                    elseif ($star == 3) { $btnActive = 'bg-yellow-500 text-white'; $btnInactive = 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100 border border-yellow-200'; }
                    else { $btnActive = 'bg-red-600 text-white'; $btnInactive = 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200'; }
                @endphp
                <a href="{{ route('admin.staff-evaluation.show', ['teacher' => $teacher, 'rating' => $star]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ request('rating') == $star ? $btnActive : $btnInactive }}">
                    {{ $star }} &#9733; ({{ $count }})
                </a>
            @endforeach
        </div>
    </div>

    {{-- Baholar ro'yxati --}}
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            Baholar va izohlar
            @if(request('rating'))
                <span class="text-sm font-normal text-gray-400">— {{ request('rating') }} yulduzli</span>
            @endif
        </h3>

        @forelse($evaluations as $eval)
        @php
            if ($eval->rating >= 4) { $cardBg = 'bg-green-50 border-green-200'; $starColor = 'text-green-500'; }
            elseif ($eval->rating == 3) { $cardBg = 'bg-yellow-50 border-yellow-200'; $starColor = 'text-yellow-500'; }
            else { $cardBg = 'bg-red-50 border-red-200'; $starColor = 'text-red-500'; }
        @endphp
        <div class="rounded-lg border p-4 mb-3 {{ $cardBg }}">
            <div class="flex items-center gap-3 mb-1">
                <div class="flex">
                    @for($i = 1; $i <= 5; $i++)
                        <span class="{{ $i <= $eval->rating ? $starColor : 'text-gray-300' }} text-lg">&#9733;</span>
                    @endfor
                </div>
                <span class="text-sm text-gray-500">
                    {{ $eval->created_at->format('d.m.Y H:i') }}
                </span>
            </div>
            @if($eval->comment)
                <p class="text-gray-700 mt-1">{{ $eval->comment }}</p>
            @endif
            @if($eval->student)
                <p class="text-xs text-gray-400 mt-2">{{ $eval->student->short_name ?? $eval->student->full_name }}</p>
            @else
                <p class="text-xs text-gray-400 mt-2">Anonim</p>
            @endif
        </div>
        @empty
        <p class="text-gray-500 text-center py-8">
            @if(request('rating'))
                {{ request('rating') }} yulduzli baholar yo'q.
            @else
                Hali baholar yo'q.
            @endif
        </p>
        @endforelse

        <div class="mt-4">
            {{ $evaluations->links() }}
        </div>
    </div>
</x-app-layout>
