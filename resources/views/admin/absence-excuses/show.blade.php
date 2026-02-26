<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-3">
                <div>
                    <a href="{{ route('admin.absence-excuses.index') }}"
                       class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition mb-1">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Barcha arizalar
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Ariza #{{ $excuse->id }}</h1>
                </div>
                <span class="px-4 py-1.5 inline-flex items-center text-sm font-semibold rounded-full
                    @if($excuse->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                    @elseif($excuse->status === 'approved') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                    @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 @endif">
                    @if($excuse->status === 'pending')
                        <span class="w-2 h-2 rounded-full bg-yellow-500 mr-2 animate-pulse"></span>
                    @elseif($excuse->status === 'approved')
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                    {{ $excuse->status_label }}
                </span>
            </div>

            @if(session('success'))
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg mb-5 flex items-center">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg mb-5 flex items-center">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            {{-- ═══════ BITTA KATTA CONTAINER ═══════ --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="margin-bottom: 20px;">

                {{-- 1-QISM: Talaba | Ariza | Davr --}}
                <div style="display: flex; gap: 0;">

                    {{-- Talaba ma'lumotlari --}}
                    <div style="flex: 1; border-right: 1px solid #e5e7eb; padding: 16px;">
                        <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3" style="display: flex; align-items: center;">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Talaba ma'lumotlari
                        </h3>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">F.I.O</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white text-right max-w-[60%]">{{ $excuse->student_full_name }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Talaba ID</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white font-mono">{{ $excuse->student?->student_id_number ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">HEMIS ID</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white font-mono">{{ $excuse->student_hemis_id }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Guruh</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->group_name ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Fakultet</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white text-right max-w-[60%]">{{ $excuse->department_name ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Ariza tafsilotlari --}}
                    <div style="flex: 1; border-right: 1px solid #e5e7eb; padding: 16px;">
                        <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3" style="display: flex; align-items: center;">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Ariza tafsilotlari
                        </h3>
                        <dl class="space-y-2">
                            <div class="flex justify-between items-start">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Sabab turi</dt>
                                <dd class="text-right max-w-[65%]">
                                    <span class="inline-flex px-2.5 py-0.5 text-sm font-bold rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $excuse->reason_label }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Hujjat seria/raqami</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->doc_number ?: '-' }}</dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Talab qilinadigan hujjat</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white text-right max-w-[55%]">{{ $excuse->reason_document }}</dd>
                            </div>
                            @if($excuse->reason_note)
                                <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Eslatma</dt>
                                    <dd class="text-sm font-bold text-amber-600 dark:text-amber-400 italic text-right max-w-[55%]">{{ $excuse->reason_note }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Yuborilgan</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->created_at->format('d.m.Y H:i') }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Qoldirilgan davr --}}
                    <div style="flex: 1; padding: 16px;">
                        <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3" style="display: flex; align-items: center;">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Qoldirilgan davr
                        </h3>
                        <div class="text-center">
                            <div class="flex items-center justify-center gap-2 mb-3">
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->start_date->format('d.m.Y') }}</p>
                                    <p class="text-xs text-gray-400">boshlanish</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->end_date->format('d.m.Y') }}</p>
                                    <p class="text-xs text-gray-400">tugash</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xl font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                {{ $daysCount }} kun
                            </span>
                            <p class="text-xs text-gray-400 mt-1">yakshanbasiz</p>
                            @if($excuse->reason_max_days)
                                <p class="text-sm mt-2">
                                    Maks: <span class="font-bold text-gray-700 dark:text-gray-300">{{ $excuse->reason_max_days }} kun</span>
                                    @if($daysCount > $excuse->reason_max_days)
                                        <span class="text-red-600 dark:text-red-400 font-bold ml-1">(limit oshgan!)</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Ajratuvchi chiziq --}}
                <div style="border-top: 1px solid #e5e7eb;"></div>

                {{-- 2-QISM: Ko'rib chiqish/Qo'shimcha + Asos hujjati --}}
                <div style="display: flex; gap: 0;">

                    {{-- Chap: Ko'rib chiqish natijasi yoki Qo'shimcha ma'lumot --}}
                    <div style="flex: 1; border-right: 1px solid #e5e7eb; padding: 16px;">
                        @if(!$excuse->isPending())
                            <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3" style="display: flex; align-items: center;">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                Ko'rib chiqish natijasi
                            </h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Ko'rib chiqqan</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->reviewed_by_name }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Sana</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->reviewed_at->format('d.m.Y H:i') }}</dd>
                                </div>
                            </dl>
                            @if($excuse->isRejected() && $excuse->rejection_reason)
                                <div class="mt-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                    <p class="text-sm font-bold text-red-600 dark:text-red-400 mb-1">Rad etish sababi:</p>
                                    <p class="text-sm text-red-700 dark:text-red-300">{{ $excuse->rejection_reason }}</p>
                                </div>
                            @endif
                            @if($excuse->isApproved() && $excuse->approved_pdf_path)
                                <div class="mt-3">
                                    <a href="{{ route('admin.absence-excuses.download-pdf', $excuse->id) }}" target="_blank"
                                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Tasdiqlangan PDF
                                    </a>
                                </div>
                            @endif
                            @if($excuse->description)
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <p class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-1">Talaba izohi</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $excuse->description }}</p>
                                </div>
                            @endif
                        @else
                            <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3" style="display: flex; align-items: center;">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Qo'shimcha ma'lumot
                            </h3>
                            @if($excuse->description)
                                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-1">Talaba izohi</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">{{ $excuse->description }}</p>
                            @endif
                            <dl class="space-y-2">
                                <div class="flex justify-between {{ $excuse->description ? 'border-t border-gray-100 dark:border-gray-700 pt-2' : '' }}">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Ariza raqami</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">#{{ $excuse->id }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Nazoratlar soni</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $excuse->makeups->count() }} ta</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Holat</dt>
                                    <dd>
                                        <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 mr-1.5 animate-pulse"></span>
                                            Kutilmoqda
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        @endif
                    </div>

                    {{-- O'ng: Asos hujjati --}}
                    <div style="flex: 1; padding: 16px;">
                        <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3" style="display: flex; align-items: center;">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            Asos hujjati
                        </h3>
                        @php
                            $ext = strtolower(pathinfo($excuse->file_original_name, PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        @endphp
                        @if($isImage)
                            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 mb-3">
                                <img src="{{ asset('storage/' . $excuse->file_path) }}" alt="Asos hujjat"
                                     class="w-full h-auto object-contain" style="max-height: 180px;">
                            </div>
                        @else
                            <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 mb-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $excuse->file_original_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ $ext }} fayl</p>
                                </div>
                            </div>
                        @endif
                        <div class="flex gap-3">
                            <a href="{{ route('admin.absence-excuses.download', $excuse->id) }}" target="_blank"
                               class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Ko'rish
                            </a>
                            <a href="{{ route('admin.absence-excuses.download', $excuse->id) }}" download
                               class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Yuklab olish
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Tasdiqlash / Rad etish tugmalari (container ichida) --}}
                @if($excuse->isPending())
                    <div style="border-top: 1px solid #e5e7eb; padding: 16px;" x-data="{ showReject: false }">
                        <div class="flex items-center gap-3">
                            <form method="POST" action="{{ route('admin.absence-excuses.approve', $excuse->id) }}"
                                  onsubmit="return confirm('Arizani tasdiqlashni xohlaysizmi? PDF hujjat yaratiladi.')">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Tasdiqlash
                                </button>
                            </form>
                            <button @click="showReject = !showReject"
                                    class="inline-flex items-center px-5 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Rad etish
                            </button>
                        </div>
                        <div x-show="showReject" x-transition class="mt-3" style="max-width: 400px;">
                            <form method="POST" action="{{ route('admin.absence-excuses.reject', $excuse->id) }}">
                                @csrf
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Rad etish sababi</label>
                                <textarea name="rejection_reason" rows="3" required maxlength="500"
                                          class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring-red-500 text-sm mb-2"
                                          placeholder="Rad etish sababini yozing..."></textarea>
                                @error('rejection_reason')
                                    <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="submit"
                                        class="px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded-lg hover:bg-red-800 transition">
                                    Rad etishni tasdiqlash
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══════ TABLE: Qayta topshirish nazoratlari — alohida pastda ═══════ --}}
            @if($excuse->makeups->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="margin-bottom: 20px;">
                    <div class="px-4 h-12 flex items-center justify-between rounded-t-xl" style="background-color: #1e40af;">
                        <h3 class="text-base font-bold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Qayta topshirish nazoratlari
                        </h3>
                        <span class="px-3 py-1 text-sm font-bold text-white rounded-full" style="background-color: #1e3a8a;">{{ $excuse->makeups->count() }} ta</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700/50 border-b-2 border-gray-200 dark:border-gray-600">
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider w-14">#</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Fan nomi</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Nazorat turi</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Qayta topshirish sanasi</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Holat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($excuse->makeups as $i => $makeup)
                                    @php
                                        $typeColors = [
                                            'jn' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            'mt' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                            'oski' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                            'test' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                        ];
                                        $typeColor = $typeColors[$makeup->assessment_type] ?? 'bg-gray-100 text-gray-700';
                                    @endphp
                                    <tr class="{{ $i % 2 === 1 ? 'bg-gray-50 dark:bg-gray-700/30' : '' }} border-b border-gray-100 dark:border-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/10 transition">
                                        <td class="px-6 py-4 text-base text-gray-400 font-bold">{{ $i + 1 }}</td>
                                        <td class="px-6 py-4 text-base font-bold text-gray-900 dark:text-white">{{ $makeup->subject_name }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-3 py-1 text-sm font-bold rounded-full {{ $typeColor }}">
                                                {{ $makeup->assessment_type_label }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-700 dark:text-gray-300">
                                            @if($makeup->makeup_date)
                                                <span class="font-semibold">{{ $makeup->makeup_date->format('d.m.Y') }}</span>
                                                @if($makeup->makeup_end_date)
                                                    <span class="text-gray-400 mx-1">&mdash;</span>
                                                    <span class="font-semibold">{{ $makeup->makeup_end_date->format('d.m.Y') }}</span>
                                                @elseif($makeup->assessment_type === 'jn' && $excuse->end_date)
                                                    <span class="text-gray-400 mx-1">&mdash;</span>
                                                    <span class="font-semibold">{{ $excuse->end_date->format('d.m.Y') }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400 italic">Belgilanmagan</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-3 py-1 text-sm font-bold rounded-full
                                                @if($makeup->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                                @elseif($makeup->status === 'scheduled') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                                @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 @endif">
                                                {{ $makeup->status_label }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
