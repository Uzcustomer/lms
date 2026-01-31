<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('O\'qituvchi ma\'lumotlari') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex flex-col md:flex-row">
                        <div class="md:w-1/3 mb-6 md:mb-0 flex justify-center">
                            @if($teacher->image)
                                <img src="{{ $teacher->image }}" alt="{{ $teacher->full_name }}" class="w-48 h-48 object-cover rounded-full shadow-lg">
                            @else
                                <div class="w-48 h-48 rounded-full bg-gray-300 flex items-center justify-center shadow-lg">
                                    <span class="text-4xl text-gray-600">{{ strtoupper(substr($teacher->first_name, 0, 1) . substr($teacher->second_name, 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="md:w-2/3 md:pl-8">
                            <h3 class="text-2xl font-bold mb-4">{{ $teacher->full_name }}</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="mb-2"><span class="font-semibold">Lavozim:</span> {{ $teacher->staff_position }}</p>
                                    <p class="mb-2"><span class="font-semibold">Kafedra:</span> {{ $teacher->department }}</p>
                                    <p class="mb-2"><span class="font-semibold">Mutaxassislik:</span> {{ $teacher->specialty }}</p>
                                </div>
                                <div>
                                    <p class="mb-2"><span class="font-semibold">Ishga kirgan yil:</span> {{ $teacher->year_of_enter }}</p>
                                    <p class="mb-2"><span class="font-semibold">Ish turi:</span> {{ $teacher->employment_form }}</p>
                                    <p class="mb-2"><span class="font-semibold">Status:</span>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            {{ $teacher->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $teacher->status === 'active' ? 'Faol' : 'Nofaol' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 border-t pt-6">
                        <h4 class="text-lg font-semibold mb-4">Qo'shimcha ma'lumotlar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="mb-2"><span class="font-semibold">Xodim ID:</span> {{ $teacher->employee_id_number }}</p>
                                <p class="mb-2"><span class="font-semibold">HEMIS ID:</span> {{ $teacher->hemis_id }}</p>
                            </div>
                            <div>
                                <p class="mb-2"><span class="font-semibold">Shartnoma:</span> {{ $teacher->contract_number }}</p>
                                <p class="mb-2"><span class="font-semibold">Sana:</span> {{ $teacher->contract_date }}</p>
                            </div>
                            <div>
                                <p class="mb-2"><span class="font-semibold">Buyruq:</span> {{ $teacher->decree_number }}</p>
                                <p class="mb-2"><span class="font-semibold">Sana:</span> {{ $teacher->decree_date }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-teacher-app-layout>
