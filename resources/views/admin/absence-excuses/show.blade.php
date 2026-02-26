<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <a href="{{ route('admin.absence-excuses.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">&larr; Orqaga</a>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">Ariza #{{ $excuse->id }}</h1>
                </div>
                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
                    bg-{{ $excuse->status_color }}-100 text-{{ $excuse->status_color }}-800">
                    {{ $excuse->status_label }}
                </span>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Asosiy ma'lumotlar --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Talaba ma'lumotlari --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Talaba ma'lumotlari</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">FIO</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->student_full_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">HEMIS ID</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->student_hemis_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Guruh</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->group_name ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Fakultet</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->department_name ?? '-' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Ariza tafsilotlari --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Ariza tafsilotlari</h3>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Sabab</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white font-medium">{{ $excuse->reason_label }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Sanalar</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ $excuse->start_date->format('d.m.Y') }} - {{ $excuse->end_date->format('d.m.Y') }}
                                        <span class="text-gray-500 text-xs">({{ $excuse->start_date->diffInDays($excuse->end_date) + 1 }} kun)</span>
                                    </p>
                                </div>
                            </div>

                            {{-- Talab qilinadigan hujjat va max kun --}}
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                <div class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div class="text-sm">
                                        <p class="text-blue-800 dark:text-blue-300"><span class="font-medium">Talab qilinadigan hujjat:</span> {{ $excuse->reason_document }}</p>
                                        @if($excuse->reason_max_days)
                                            <p class="text-blue-700 dark:text-blue-400 text-xs mt-1">Maksimum {{ $excuse->reason_max_days }} kun ruxsat etiladi</p>
                                        @endif
                                        @if($excuse->reason_note)
                                            <p class="text-blue-600 dark:text-blue-400 text-xs mt-1 italic">{{ $excuse->reason_note }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if($excuse->description)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Izoh</label>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->description }}</p>
                                </div>
                            @endif

                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Yuborilgan sana</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Yuklangan hujjat --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Yuklangan hujjat</h3>

                        @php
                            $ext = strtolower(pathinfo($excuse->file_original_name, PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png']);
                        @endphp

                        @if($isImage)
                            <div class="mb-4">
                                <img src="{{ asset('storage/' . $excuse->file_path) }}" alt="Hujjat"
                                     class="max-w-full h-auto rounded-lg border border-gray-200" style="max-height: 500px;">
                            </div>
                        @endif

                        <a href="{{ route('admin.absence-excuses.download', $excuse->id) }}" target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            {{ $excuse->file_original_name }}
                        </a>
                    </div>

                    {{-- Ko'rib chiqilgan bo'lsa --}}
                    @if(!$excuse->isPending())
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Ko'rib chiqish natijasi</h3>
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Ko'rib chiqqan</label>
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->reviewed_by_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Sana</label>
                                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $excuse->reviewed_at->format('d.m.Y H:i') }}</p>
                                    </div>
                                </div>
                                @if($excuse->isRejected() && $excuse->rejection_reason)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Rad etish sababi</label>
                                        <p class="mt-1 text-sm text-red-600">{{ $excuse->rejection_reason }}</p>
                                    </div>
                                @endif
                                @if($excuse->isApproved() && $excuse->approved_pdf_path)
                                    <div>
                                        <a href="{{ route('admin.absence-excuses.download-pdf', $excuse->id) }}" target="_blank"
                                           class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 transition">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            Tasdiqlangan PDF ni ko'rish
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- O'ng panel: Tasdiqlash/Rad etish --}}
                <div class="lg:col-span-1">
                    @if($excuse->isPending())
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 sticky top-20" x-data="{ showReject: false }">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Qaror</h3>

                            {{-- Tasdiqlash --}}
                            <form method="POST" action="{{ route('admin.absence-excuses.approve', $excuse->id) }}"
                                  onsubmit="return confirm('Arizani tasdiqlashni xohlaysizmi? PDF hujjat yaratiladi.')">
                                @csrf
                                <button type="submit"
                                        class="w-full mb-3 px-4 py-3 bg-green-600 text-white font-semibold rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Tasdiqlash
                                </button>
                            </form>

                            {{-- Rad etish --}}
                            <button @click="showReject = !showReject"
                                    class="w-full mb-3 px-4 py-3 bg-red-600 text-white font-semibold rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Rad etish
                            </button>

                            <div x-show="showReject" x-transition class="mt-3">
                                <form method="POST" action="{{ route('admin.absence-excuses.reject', $excuse->id) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Rad etish sababi <span class="text-red-500">*</span>
                                        </label>
                                        <textarea name="rejection_reason" rows="3" required maxlength="500"
                                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                                                  placeholder="Rad etish sababini yozing..."></textarea>
                                        @error('rejection_reason')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit"
                                            class="w-full px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded-md hover:bg-red-800 transition">
                                        Rad etishni tasdiqlash
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
