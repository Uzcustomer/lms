<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14 max-w-7xl mx-auto">

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
                <div class="flex items-center gap-3">
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

                    @if($excuse->isApproved() && $excuse->approved_pdf_path)
                        <a href="{{ route('admin.absence-excuses.download-pdf', $excuse->id) }}" target="_blank"
                           class="inline-flex items-center px-4 py-1.5 bg-green-600 text-white text-sm font-medium rounded-full hover:bg-green-700 transition">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            PDF
                        </a>
                    @endif
                </div>
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

            {{-- 1-qator: Talaba + Ariza tafsilotlari yonma-yon --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

                {{-- Talaba ma'lumotlari --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center">
                            <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Talaba ma'lumotlari
                        </h3>
                    </div>
                    <div class="p-5">
                        <dl class="space-y-3">
                            <div class="flex justify-between items-start">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">F.I.O</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white text-right">{{ $excuse->student_full_name }}</dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Talaba ID</dt>
                                <dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $excuse->student?->student_id_number ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">HEMIS ID</dt>
                                <dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $excuse->student_hemis_id }}</dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Guruh</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ $excuse->group_name ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Fakultet / Kafedra</dt>
                                <dd class="text-sm text-gray-900 dark:text-white text-right max-w-[60%]">{{ $excuse->department_name ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Ariza tafsilotlari --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center">
                            <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Ariza tafsilotlari
                        </h3>
                    </div>
                    <div class="p-5">
                        <dl class="space-y-3">
                            <div class="flex justify-between items-start">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Sabab turi</dt>
                                <dd class="text-right max-w-[65%]">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $excuse->reason_label }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Asos hujjat seria/raqami</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ $excuse->doc_number ?: '-' }}</dd>
                            </div>
                            <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Talab qilinadigan hujjat</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300 text-right max-w-[60%]">{{ $excuse->reason_document }}</dd>
                            </div>
                            @if($excuse->reason_note)
                                <div class="flex justify-between items-start border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Eslatma</dt>
                                    <dd class="text-xs text-amber-600 dark:text-amber-400 italic text-right max-w-[60%]">{{ $excuse->reason_note }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between items-center border-t border-gray-100 dark:border-gray-700 pt-3">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Yuborilgan sana</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ $excuse->created_at->format('d.m.Y H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- 2-qator: Sanalar + Asos hujjat + Qaror (agar pending) yonma-yon --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

                {{-- Qoldirilgan kunlar --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <div class="text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Qoldirilgan davr</p>
                        <div class="flex items-center justify-center gap-3 mb-3">
                            <div class="text-center">
                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $excuse->start_date->format('d.m.Y') }}</p>
                                <p class="text-xs text-gray-400">boshlanish</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            <div class="text-center">
                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $excuse->end_date->format('d.m.Y') }}</p>
                                <p class="text-xs text-gray-400">tugash</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xl font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                            {{ $daysCount }} kun
                        </span>
                        <p class="text-xs text-gray-400 mt-1">yakshanbasiz</p>
                        @if($excuse->reason_max_days)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                Maks: <span class="font-semibold">{{ $excuse->reason_max_days }} kun</span>
                                @if($daysCount > $excuse->reason_max_days)
                                    <span class="text-red-600 dark:text-red-400 font-semibold">(oshgan!)</span>
                                @endif
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Asos hujjati --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center">
                            <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            Asos hujjati
                        </h3>
                        <a href="{{ route('admin.absence-excuses.download', $excuse->id) }}" target="_blank"
                           class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Yuklab olish</a>
                    </div>
                    @php
                        $ext = strtolower(pathinfo($excuse->file_original_name, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    @endphp
                    <div class="p-4">
                        @if($isImage)
                            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900">
                                <img src="{{ asset('storage/' . $excuse->file_path) }}" alt="Asos hujjat"
                                     class="w-full h-auto object-contain" style="max-height: 250px;">
                            </div>
                        @else
                            <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $excuse->file_original_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ $ext }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Qaror / Ko'rib chiqish natijasi --}}
                @if($excuse->isPending())
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" x-data="{ showReject: false }">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Qaror</h3>
                        </div>
                        <div class="p-5 space-y-3">
                            <form method="POST" action="{{ route('admin.absence-excuses.approve', $excuse->id) }}"
                                  onsubmit="return confirm('Arizani tasdiqlashni xohlaysizmi?')">
                                @csrf
                                <button type="submit"
                                        class="w-full px-4 py-2.5 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition flex items-center justify-center text-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Tasdiqlash
                                </button>
                            </form>
                            <button @click="showReject = !showReject"
                                    class="w-full px-4 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition flex items-center justify-center text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Rad etish
                            </button>
                            <div x-show="showReject" x-transition class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                <form method="POST" action="{{ route('admin.absence-excuses.reject', $excuse->id) }}">
                                    @csrf
                                    <textarea name="rejection_reason" rows="3" required maxlength="500"
                                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring-red-500 text-sm mb-2"
                                              placeholder="Rad etish sababini yozing..."></textarea>
                                    @error('rejection_reason')
                                        <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <button type="submit"
                                            class="w-full px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded-lg hover:bg-red-800 transition">
                                        Tasdiqlash
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Ko'rib chiqish natijasi</h3>
                        </div>
                        <div class="p-5">
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Ko'rib chiqqan</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $excuse->reviewed_by_name }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Sana</dt>
                                    <dd class="text-sm text-gray-900 dark:text-white">{{ $excuse->reviewed_at->format('d.m.Y H:i') }}</dd>
                                </div>
                            </dl>
                            @if($excuse->isRejected() && $excuse->rejection_reason)
                                <div class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                    <p class="text-xs font-medium text-red-600 dark:text-red-400 mb-1">Rad etish sababi:</p>
                                    <p class="text-sm text-red-700 dark:text-red-300">{{ $excuse->rejection_reason }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Talaba izohi (agar mavjud) --}}
            @if($excuse->description)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-5 p-5">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Talaba izohi</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $excuse->description }}</p>
                </div>
            @endif

            {{-- 3-qator: Qayta topshirish nazoratlari — full width --}}
            @if($excuse->makeups->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center">
                            <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Qayta topshirish nazoratlari
                        </h3>
                        <span class="px-2.5 py-0.5 text-xs font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 rounded-full">{{ $excuse->makeups->count() }} ta</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700/50">
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">#</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fan nomi</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nazorat turi</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qayta topshirish sanasi</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Holat</th>
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
                                        <td class="px-5 py-3 text-gray-400 font-medium">{{ $i + 1 }}</td>
                                        <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $makeup->subject_name }}</td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full {{ $typeColor }}">
                                                {{ $makeup->assessment_type_label }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-gray-700 dark:text-gray-300">
                                            @if($makeup->makeup_date)
                                                <span class="font-medium">{{ $makeup->makeup_date->format('d.m.Y') }}</span>
                                                @if($makeup->makeup_end_date)
                                                    <span class="text-gray-400 mx-1">&mdash;</span>
                                                    <span class="font-medium">{{ $makeup->makeup_end_date->format('d.m.Y') }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400 italic">Belgilanmagan</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full
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

        </div>
    </div>
</x-app-layout>
