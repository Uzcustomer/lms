<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Sababli dars qoldirish arizasi
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    @if(session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('student.absence-excuses.store') }}"
                          enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        {{-- Sabab tanlash --}}
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">
                                Sabab <span class="text-red-500">*</span>
                            </label>
                            <select name="reason" id="reason" required
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sababni tanlang</option>
                                @foreach($reasons as $key => $label)
                                    <option value="{{ $key }}" {{ old('reason') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Sanalar --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Boshlanish sanasi <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('start_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Tugash sanasi <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" required
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Izoh --}}
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                                Izoh (ixtiyoriy)
                            </label>
                            <textarea name="description" id="description" rows="3" maxlength="1000"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Qo'shimcha ma'lumot...">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Fayl yuklash --}}
                        <div>
                            <label for="file" class="block text-sm font-medium text-gray-700 mb-1">
                                Tasdiqlovchi hujjat (spravka) <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="file" id="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0 file:text-sm file:font-semibold
                                          file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="mt-1 text-xs text-gray-500">
                                Ruxsat etilgan formatlar: PDF, JPG, PNG, DOC, DOCX. Maksimum hajm: 10MB
                            </p>
                            @error('file')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between pt-4">
                            <a href="{{ route('student.absence-excuses.index') }}"
                               class="text-gray-600 hover:text-gray-800 text-sm">
                                Orqaga
                            </a>
                            <button type="submit"
                                    class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-md
                                           hover:bg-indigo-700 focus:outline-none focus:ring-2
                                           focus:ring-indigo-500 focus:ring-offset-2 transition">
                                Ariza yuborish
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
