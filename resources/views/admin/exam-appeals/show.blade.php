<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            {{-- Sarlavha --}}
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.exam-appeals.index') }}" class="p-2 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Apellyatsiya #{{ $appeal->id }}</h1>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                    bg-{{ $appeal->getStatusColor() }}-100 text-{{ $appeal->getStatusColor() }}-700">
                    <svg class="mr-1.5 h-2 w-2 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                    {{ $appeal->getStatusLabel() }}
                </span>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {{-- Chap tomon: Ariza ma'lumotlari --}}
                <div class="lg:col-span-2 space-y-5">

                    {{-- Talaba ma'lumotlari --}}
                    <div class="bg-white rounded-lg shadow-sm border p-5">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Talaba ma'lumotlari</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">F.I.O</p>
                                <p class="text-sm font-semibold text-gray-800">{{ $appeal->student->full_name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Talaba ID</p>
                                <p class="text-sm font-semibold text-gray-800">{{ $appeal->student->student_id_number ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Guruh</p>
                                <p class="text-sm text-gray-700">{{ $appeal->student->group_name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Topshirilgan sana</p>
                                <p class="text-sm text-gray-700">{{ $appeal->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Imtihon ma'lumotlari --}}
                    <div class="bg-white rounded-lg shadow-sm border p-5">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Imtihon ma'lumotlari</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Fan nomi</p>
                                <p class="text-sm font-semibold text-gray-800">{{ $appeal->subject_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Nazorat turi</p>
                                <p class="text-sm text-gray-700">{{ $appeal->training_type_name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Joriy baho</p>
                                <p class="text-lg font-bold text-gray-800">{{ $appeal->current_grade }} <span class="text-sm font-normal text-gray-500">ball</span></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">O'qituvchi</p>
                                <p class="text-sm text-gray-700">{{ $appeal->employee_name ?? '-' }}</p>
                            </div>
                            @if($appeal->exam_date)
                            <div>
                                <p class="text-xs text-gray-500">Imtihon sanasi</p>
                                <p class="text-sm text-gray-700">{{ $appeal->exam_date->format('d.m.Y') }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Apellyatsiya sababi --}}
                    <div class="bg-white rounded-lg shadow-sm border p-5">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Apellyatsiya sababi</h3>
                        <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $appeal->reason }}</p>

                        @if($appeal->file_path)
                            <div class="mt-4 pt-3 border-t">
                                <a href="{{ route('admin.exam-appeals.download', $appeal->id) }}"
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 transition">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    <span class="text-sm text-gray-700 font-medium">{{ $appeal->file_original_name }}</span>
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- Ko'rib chiqish natijasi (agar bor bo'lsa) --}}
                    @if(in_array($appeal->status, ['approved', 'rejected']))
                    <div class="bg-{{ $appeal->getStatusColor() }}-50 rounded-lg border border-{{ $appeal->getStatusColor() }}-200 p-5">
                        <h3 class="text-sm font-bold text-{{ $appeal->getStatusColor() }}-700 uppercase tracking-wider mb-3">Ko'rib chiqish natijasi</h3>
                        <div class="space-y-2">
                            @if($appeal->reviewed_by_name)
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Ko'rib chiqqan</span>
                                <span class="text-sm font-medium text-gray-800">{{ $appeal->reviewed_by_name }}</span>
                            </div>
                            @endif
                            @if($appeal->reviewed_at)
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Sana</span>
                                <span class="text-sm text-gray-700">{{ $appeal->reviewed_at->format('d.m.Y H:i') }}</span>
                            </div>
                            @endif
                            @if($appeal->new_grade !== null)
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Yangi baho</span>
                                <span class="text-sm font-bold text-green-700">{{ $appeal->new_grade }} ball</span>
                            </div>
                            @endif
                            @if($appeal->review_comment)
                            <div class="mt-2 pt-2 border-t border-{{ $appeal->getStatusColor() }}-200">
                                <p class="text-xs text-gray-500 mb-1">Izoh:</p>
                                <p class="text-sm text-gray-700">{{ $appeal->review_comment }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- O'ng tomon: Harakat paneli --}}
                <div class="space-y-5">
                    @if(in_array($appeal->status, ['pending', 'reviewing']))

                        {{-- Qabul qilish formasi --}}
                        <div class="bg-white rounded-lg shadow-sm border p-5">
                            <h3 class="text-sm font-bold text-green-600 uppercase tracking-wider mb-3">Qabul qilish</h3>
                            <form method="POST" action="{{ route('admin.exam-appeals.approve', $appeal->id) }}">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Yangi baho (ixtiyoriy)</label>
                                        <input type="number" name="new_grade" step="0.1" min="0" max="100"
                                               placeholder="Yangi baho kiriting..."
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Izoh (ixtiyoriy)</label>
                                        <textarea name="review_comment" rows="3"
                                                  placeholder="Qo'shimcha izoh..."
                                                  class="w-full rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"></textarea>
                                    </div>
                                    <button type="submit"
                                            class="w-full px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition text-sm"
                                            onclick="return confirm('Apellyatsiyani qabul qilmoqchimisiz?')">
                                        Qabul qilish
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Rad etish formasi --}}
                        <div class="bg-white rounded-lg shadow-sm border p-5">
                            <h3 class="text-sm font-bold text-red-600 uppercase tracking-wider mb-3">Rad etish</h3>
                            <form method="POST" action="{{ route('admin.exam-appeals.reject', $appeal->id) }}">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Rad etish sababi <span class="text-red-500">*</span></label>
                                        <textarea name="review_comment" rows="3" required minlength="5"
                                                  placeholder="Nima uchun rad etilyapti..."
                                                  class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500 @error('review_comment') border-red-300 @enderror"></textarea>
                                        @error('review_comment')
                                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit"
                                            class="w-full px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition text-sm"
                                            onclick="return confirm('Apellyatsiyani rad etmoqchimisiz?')">
                                        Rad etish
                                    </button>
                                </div>
                            </form>
                        </div>

                    @else
                        <div class="bg-gray-50 rounded-lg border p-5 text-center">
                            <svg class="w-10 h-10 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm text-gray-500">Bu ariza allaqachon ko'rib chiqilgan</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
