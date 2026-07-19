<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            O'quv reja solishtirma
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            <div class="mb-4 flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('admin.oquv-reja.index') }}#solishtirish" class="text-blue-600 hover:underline text-sm">&larr; O'quv reja to'g'riligi</a>
                <a href="{{ route('admin.oquv-reja.compare-export', ['reference_id' => $reference->id, 'working_id' => $working->id]) }}"
                   class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                    Excelga yuklab olish
                </a>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                <div class="text-sm text-gray-600">
                    <span class="font-semibold text-blue-700">{{ $reference->name }}</span>
                    (namunaviy)
                    <span class="mx-2">&harr;</span>
                    <span class="font-semibold text-purple-700">{{ $working->name }}</span>
                    (ishchi)
                </div>
            </div>

            @include('admin.oquv-reja._compare-table', ['comparison' => $comparison])

        </div>
    </div>
</x-app-layout>
