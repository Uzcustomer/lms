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

            {{-- ═══════ 1-QATOR: Talaba | Ariza | Qoldirilgan davr ═══════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

                {{-- Talaba ma'lumotlari --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3 bg-indigo-600 dark:bg-indigo-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <h3 class="text-base font-bold text-white">Talaba ma'lumotlari</h3>
                    </div>
                    <div class="p-5">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">F.I.O</dt>
                                <dd class="font-bold text-gray-900 dark:text-white text-right max-w-[60%]">{{ $excuse->student_full_name }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-gray-500 dark:text-gray-400">Talaba ID</dt>
                                <dd class="font-semibold text-gray-900 dark:text-white font-mono">{{ $excuse->student?->student_id_number ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-gray-500 dark:text-gray-400">HEMIS ID</dt>
                                <dd class="text-gray-900 dark:text-white font-mono">{{ $excuse->student_hemis_id }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-gray-500 dark:text-gray-400">Guruh</dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">{{ $excuse->group_name ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-gray-500 dark:text-gray-400">Fakultet</dt>
                                <dd class="text-gray-900 dark:text-white text-right max-w-[60%]">{{ $excuse->department_name ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Ariza tafsilotlari --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3 bg-blue-600 dark:bg-blue-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <h3 class="text-base font-bold text-white">Ariza tafsilotlari</h3>
                    </div>
                    <div class="p-5">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between items-start">
                                <dt class="text-gray-500 dark:text-gray-400">Sabab turi</dt>
                                <dd class="text-right max-w-[65%]">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-bold rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $excuse->reason_label }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-gray-500 dark:text-gray-400">Hujjat seria/raqami</dt>
                                <dd class="font-bold text-gray-900 dark:text-white">{{ $excuse->doc_number ?: '-' }}</dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-gray-500 dark:text-gray-400">Talab qilinadigan hujjat</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300 text-right max-w-[55%]">{{ $excuse->reason_document }}</dd>
                            </div>
                            @if($excuse->reason_note)
                                <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Eslatma</dt>
                                    <dd class="text-xs text-amber-600 dark:text-amber-400 italic text-right max-w-[55%]">{{ $excuse->reason_note }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-gray-500 dark:text-gray-400">Yuborilgan</dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">{{ $excuse->created_at->format('d.m.Y H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Qoldirilgan davr --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3 bg-orange-500 dark:bg-orange-600 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <h3 class="text-base font-bold text-white">Qoldirilgan davr</h3>
                    </div>
                    <div class="p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-4">
                            <div>
                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $excuse->start_date->format('d.m.Y') }}</p>
                                <p class="text-xs text-gray-400">boshlanish</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            <div>
                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $excuse->end_date->format('d.m.Y') }}</p>
                                <p class="text-xs text-gray-400">tugash</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-5 py-2 rounded-full text-2xl font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                            {{ $daysCount }} kun
                        </span>
                        <p class="text-xs text-gray-400 mt-1">yakshanbasiz</p>
                        @if($excuse->reason_max_days)
                            <p class="text-xs mt-2">
                                Maks: <span class="font-bold text-gray-700 dark:text-gray-300">{{ $excuse->reason_max_days }} kun</span>
                                @if($daysCount > $excuse->reason_max_days)
                                    <span class="text-red-600 dark:text-red-400 font-bold ml-1">(limit oshgan!)</span>
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════ 2-QATOR: Ko'rib chiqish / Qaror + Asos hujjati ═══════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

                {{-- Ko'rib chiqish natijasi yoki Talaba izohi --}}
                @if(!$excuse->isPending())
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3 bg-emerald-600 dark:bg-emerald-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <h3 class="text-base font-bold text-white">Ko'rib chiqish natijasi</h3>
                        </div>
                        <div class="p-5">
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Ko'rib chiqqan</dt>
                                    <dd class="font-bold text-gray-900 dark:text-white">{{ $excuse->reviewed_by_name }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Sana</dt>
                                    <dd class="font-semibold text-gray-900 dark:text-white">{{ $excuse->reviewed_at->format('d.m.Y H:i') }}</dd>
                                </div>
                            </dl>
                            @if($excuse->isRejected() && $excuse->rejection_reason)
                                <div class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                    <p class="text-xs font-bold text-red-600 dark:text-red-400 mb-1">Rad etish sababi:</p>
                                    <p class="text-sm text-red-700 dark:text-red-300">{{ $excuse->rejection_reason }}</p>
                                </div>
                            @endif
                            @if($excuse->isApproved() && $excuse->approved_pdf_path)
                                <div class="mt-4">
                                    <a href="{{ route('admin.absence-excuses.download-pdf', $excuse->id) }}" target="_blank"
                                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Tasdiqlangan PDF
                                    </a>
                                </div>
                            @endif
                            @if($excuse->description)
                                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Talaba izohi</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $excuse->description }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3 bg-gray-600 dark:bg-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h3 class="text-base font-bold text-white">Qo'shimcha ma'lumot</h3>
                        </div>
                        <div class="p-5">
                            @if($excuse->description)
                                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Talaba izohi</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">{{ $excuse->description }}</p>
                            @endif
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Ariza raqami</dt>
                                    <dd class="font-bold text-gray-900 dark:text-white">#{{ $excuse->id }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Nazoratlar soni</dt>
                                    <dd class="font-bold text-gray-900 dark:text-white">{{ $excuse->makeups->count() }} ta</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Holat</dt>
                                    <dd>
                                        <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 mr-1.5 animate-pulse"></span>
                                            Kutilmoqda
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif

                {{-- Asos hujjati --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3 bg-violet-600 dark:bg-violet-700 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        <h3 class="text-base font-bold text-white">Asos hujjati</h3>
                    </div>
                    @php
                        $ext = strtolower(pathinfo($excuse->file_original_name, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    @endphp
                    <div class="p-5">
                        {{-- Kichik preview --}}
                        @if($isImage)
                            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 mb-4">
                                <img src="{{ asset('storage/' . $excuse->file_path) }}" alt="Asos hujjat"
                                     class="w-full h-auto object-contain" style="max-height: 200px;">
                            </div>
                        @else
                            <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-4">
                                <div class="w-12 h-12 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $excuse->file_original_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ $ext }} fayl</p>
                                </div>
                            </div>
                        @endif

                        {{-- Rangli tugmalar --}}
                        <div class="flex gap-3">
                            <a href="{{ route('admin.absence-excuses.download', $excuse->id) }}" target="_blank"
                               class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Ko'rish
                            </a>
                            <a href="{{ route('admin.absence-excuses.download', $excuse->id) }}" download
                               class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-violet-600 text-white text-sm font-semibold rounded-lg hover:bg-violet-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Yuklab olish
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════ 3-QATOR: Qayta topshirish nazoratlari — full width ═══════ --}}
            @if($excuse->makeups->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-5">
                    <div class="px-5 py-3 bg-teal-600 dark:bg-teal-700 flex items-center justify-between">
                        <h3 class="text-base font-bold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Qayta topshirish nazoratlari
                        </h3>
                        <span class="px-2.5 py-0.5 text-xs font-bold bg-white/20 text-white rounded-full">{{ $excuse->makeups->count() }} ta</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700/50">
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">#</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fan nomi</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nazorat turi</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qayta topshirish sanasi</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($excuse->makeups as $i => $makeup)
                                    @php
                                        $typeColors = [
                                            'jn' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                            'mt' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                            'oski' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                            'test' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                        ];
                                        $typeColor = $typeColors[$makeup->assessment_type] ?? 'bg-gray-100 text-gray-700';
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-5 py-3.5 text-gray-400 font-bold">{{ $i + 1 }}</td>
                                        <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $makeup->subject_name }}</td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-bold rounded-full {{ $typeColor }}">
                                                {{ $makeup->assessment_type_label }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-gray-700 dark:text-gray-300">
                                            @if($makeup->makeup_date)
                                                <span class="font-semibold">{{ $makeup->makeup_date->format('d.m.Y') }}</span>
                                                @if($makeup->makeup_end_date)
                                                    <span class="text-gray-400 mx-1">&mdash;</span>
                                                    <span class="font-semibold">{{ $makeup->makeup_end_date->format('d.m.Y') }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400 italic">Belgilanmagan</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-bold rounded-full
                                                @if($makeup->status === 'completed') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300
                                                @elseif($makeup->status === 'scheduled') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                                @else bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 @endif">
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

            {{-- ═══════ Tasdiqlash / Rad etish tugmalari ═══════ --}}
            @if($excuse->isPending())
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" x-data="{ showReject: false }">
                    <div class="p-5">
                        <div class="flex gap-4">
                            <form method="POST" action="{{ route('admin.absence-excuses.approve', $excuse->id) }}" class="flex-1"
                                  onsubmit="return confirm('Arizani tasdiqlashni xohlaysizmi? PDF hujjat yaratiladi.')">
                                @csrf
                                <button type="submit"
                                        class="w-full px-6 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition flex items-center justify-center text-base">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Tasdiqlash
                                </button>
                            </form>
                            <button @click="showReject = !showReject"
                                    class="flex-1 px-6 py-3 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition flex items-center justify-center text-base">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Rad etish
                            </button>
                        </div>
                        <div x-show="showReject" x-transition class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <form method="POST" action="{{ route('admin.absence-excuses.reject', $excuse->id) }}">
                                @csrf
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Rad etish sababi</label>
                                <textarea name="rejection_reason" rows="3" required maxlength="500"
                                          class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring-red-500 text-sm mb-3"
                                          placeholder="Rad etish sababini yozing..."></textarea>
                                @error('rejection_reason')
                                    <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="submit"
                                        class="w-full px-4 py-2.5 bg-red-700 text-white text-sm font-bold rounded-lg hover:bg-red-800 transition">
                                    Rad etishni tasdiqlash
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
