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
                          enctype="multipart/form-data" class="space-y-6"
                          x-data="{
                              reason: '{{ old('reason', '') }}',
                              reasons: {{ Js::from($reasons) }},
                              get selectedReason() {
                                  return this.reason ? this.reasons[this.reason] : null;
                              }
                          }">
                        @csrf

                        {{-- Sabab tanlash --}}
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">
                                Sabab <span class="text-red-500">*</span>
                            </label>
                            <select name="reason" id="reason" required x-model="reason"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sababni tanlang</option>
                                @foreach($reasons as $key => $data)
                                    <option value="{{ $key }}" {{ old('reason') == $key ? 'selected' : '' }}>{{ $data['label'] }}</option>
                                @endforeach
                            </select>
                            @error('reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Tanlangan sabab haqida ma'lumot --}}
                        <div x-show="selectedReason" x-transition
                             class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="text-sm">
                                    <p class="font-medium text-blue-800 mb-1">Talab qilinadigan hujjat:</p>
                                    <p class="text-blue-700" x-text="selectedReason?.document"></p>
                                    <div class="mt-2 flex flex-wrap gap-3">
                                        <template x-if="selectedReason?.max_days">
                                            <span class="inline-flex items-center text-xs font-medium text-blue-700 bg-blue-100 px-2 py-1 rounded">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Maksimum: <span x-text="selectedReason?.max_days" class="ml-1"></span> kun
                                            </span>
                                        </template>
                                    </div>
                                    <template x-if="selectedReason?.note">
                                        <p class="mt-2 text-xs text-blue-600 italic" x-text="selectedReason?.note"></p>
                                    </template>
                                </div>
                            </div>
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
