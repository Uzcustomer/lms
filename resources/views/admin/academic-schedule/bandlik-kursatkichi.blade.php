<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Bandlik ko'rsatkichi
            </h2>
            <div class="text-sm text-gray-600">
                Jami komputerlar: <span class="font-bold text-indigo-700">{{ $totalComputers }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">

                @if($dateCards->isEmpty())
                    <div class="text-center py-16 border-2 border-dashed border-gray-200 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="mt-3 text-sm font-medium text-gray-900">Kelgusi kunlarda test belgilanmagan</h3>
                        <p class="mt-1 text-sm text-gray-500">Bugundan boshlab hech qaysi guruh uchun test vaqti belgilanmagan.</p>
                    </div>
                @else
                    <div class="mb-4 flex items-center gap-2">
                        <div class="w-1 h-6 bg-indigo-600 rounded-full"></div>
                        <h3 class="text-base font-semibold text-gray-800">Kelgusi kunlar</h3>
                        <span class="text-xs text-gray-500">({{ $dateCards->count() }} kun)</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach($dateCards as $card)
                            @include('admin.academic-schedule.partials.bandlik-card', ['card' => $card, 'totalComputers' => $totalComputers])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
