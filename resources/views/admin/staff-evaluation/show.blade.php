<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $user->name }} — Baholar
        </h2>
    </x-slot>

    <div class="mb-4">
        <a href="{{ route('admin.staff-evaluation.index') }}"
           class="text-blue-600 hover:text-blue-800 text-sm">
            &larr; Orqaga
        </a>
    </div>

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
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-800">QR kod</h3>
                @if($user->eval_qr_token)
                    <p class="text-sm text-gray-500 mt-1">
                        Havola: <code class="bg-gray-100 px-2 py-0.5 rounded text-xs">{{ route('staff-evaluate.form', $user->eval_qr_token) }}</code>
                    </p>
                @else
                    <p class="text-sm text-gray-400 mt-1">QR kod hali yaratilmagan.</p>
                @endif
            </div>
            <div class="flex gap-2">
                @if(!$user->eval_qr_token)
                <form method="POST" action="{{ route('admin.staff-evaluation.generate-qr', $user) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                        QR yaratish
                    </button>
                </form>
                @else
                <a href="{{ route('admin.staff-evaluation.download-qr', $user) }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 inline-block">
                    QR yuklab olish
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Baholar ro'yxati --}}
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Baholar va izohlar</h3>

        @forelse($evaluations as $eval)
        <div class="border-b border-gray-100 py-4 {{ $loop->last ? 'border-b-0' : '' }}">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="flex">
                            @for($i = 1; $i <= 5; $i++)
                                <span class="{{ $i <= $eval->rating ? 'text-yellow-400' : 'text-gray-300' }} text-lg">&#9733;</span>
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
                        <p class="text-xs text-gray-400 mt-1">{{ $eval->student->short_name ?? $eval->student->full_name }}</p>
                    @else
                        <p class="text-xs text-gray-400 mt-1">Anonim</p>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <p class="text-gray-500 text-center py-8">Hali baholar yo'q.</p>
        @endforelse

        <div class="mt-4">
            {{ $evaluations->links() }}
        </div>
    </div>
</x-app-layout>
