<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.international-students.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h2 class="font-semibold text-sm text-gray-800 leading-tight">{{ $student->full_name }}</h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">
            @if(session('success'))
                <div class="mb-3 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
            @endif

            @if(!$visaInfo)
                <div class="bg-white shadow-sm rounded-xl p-6 border border-gray-100">
                    <div class="flex items-center gap-4 mb-5">
                        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px;">{{ mb_substr($student->full_name, 0, 1) }}</div>
                        <div>
                            <div class="font-bold text-gray-800">{{ $student->full_name }}</div>
                            <div class="text-xs text-gray-500">{{ $student->group_name }} &middot; {{ $student->level_name }} &middot; {{ $student->country_name ?? '' }}</div>
                        </div>
                    </div>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto mb-3 text-yellow-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        <p class="text-sm font-medium text-yellow-700">Talaba hali viza ma'lumotlarini kiritmagan.</p>
                    </div>
                </div>
            @else
                @php
                    $rps = $visaInfo->registration_process_status ?? 'none';
                    $vps = $visaInfo->visa_process_status ?? 'none';
                    $regDays = $visaInfo->registrationDaysLeft();
                    $visaDays = $visaInfo->visaDaysLeft();
                @endphp

                {{-- Header card --}}
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-4 mb-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-4">
                            <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px;">{{ mb_substr($student->full_name, 0, 1) }}</div>
                            <div>
                                <div class="font-bold text-gray-800 text-base">{{ $student->full_name }}</div>
                                <div class="text-xs text-gray-500 flex items-center gap-2 flex-wrap">
                                    <span>{{ $student->group_name }}</span> &middot;
                                    <span>{{ $student->level_name }}</span> &middot;
                                    <span class="font-semibold text-blue-700">{{ $student->country_name ?? '-' }}</span> &middot;
                                    <span>{{ $student->department_name }}</span>
                                    @if($student->phone) &middot; <span>{{ $student->phone }}</span> @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($visaInfo->status === 'approved')
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-700 border border-green-200">Tasdiqlangan</span>
                            @elseif($visaInfo->status === 'rejected')
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700 border border-red-200">Rad etilgan</span>
                            @else
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-yellow-100 text-yellow-700 border border-yellow-200">Tekshirilmoqda</span>
                            @endif
                            @if($visaInfo->firm)
                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-blue-50 text-blue-700 border border-blue-200">{{ $visaInfo->firm_display }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    {{-- Chap: Amallar --}}
                    <div class="lg:col-span-4 space-y-4">

                        {{-- Firma --}}
                        <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
                            <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75"/></svg>
                                Firma biriktirish
                            </h4>
                            <form method="POST" action="{{ route('admin.international-students.assign-firm', $student) }}">
                                @csrf
                                <div class="flex gap-2">
                                    <select name="firm" class="flex-1 text-xs border-gray-200 rounded-lg py-2 px-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.querySelector('.firm-other').style.display=this.value==='other'?'block':'none'">
                                        <option value="">Tanlang</option>
                                        @foreach(\App\Models\StudentVisaInfo::FIRM_OPTIONS as $key => $label)
                                            <option value="{{ $key }}" {{ $visaInfo->firm === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                        <option value="other" {{ $visaInfo->firm === 'other' ? 'selected' : '' }}>Boshqa</option>
                                    </select>
                                    <button type="submit" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);" class="px-4 py-2 text-white text-xs font-semibold rounded-lg hover:opacity-90 transition">Saqlash</button>
                                </div>
                                <input type="text" name="firm_custom" value="{{ $visaInfo->firm_custom }}" placeholder="Firma nomi" class="firm-other w-full mt-2 text-xs border-gray-200 rounded-lg py-2 px-3" style="display:{{ $visaInfo->firm === 'other' ? 'block' : 'none' }};">
                            </form>
                        </div>

                        {{-- Tasdiqlash/Rad etish --}}
                        @if($visaInfo->status === 'pending')
                        <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100" x-data="{ showReject: false }">
                            <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Tekshiruv</h4>
                            @if($visaInfo->rejection_reason)
                                <div class="p-2.5 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 mb-3">{{ $visaInfo->rejection_reason }}</div>
                            @endif
                            <div class="flex gap-2 mb-2">
                                <form method="POST" action="{{ route('admin.international-students.approve', $student) }}">@csrf
                                    <button type="submit" style="background:linear-gradient(135deg,#16a34a,#22c55e);" class="px-4 py-2 text-white text-xs font-semibold rounded-lg hover:opacity-90 transition" onclick="return confirm('Tasdiqlaysizmi?')">Tasdiqlash</button>
                                </form>
                                <button type="button" @click="showReject=!showReject" class="px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition">Rad etish</button>
                            </div>
                            <div x-show="showReject" x-transition>
                                <form method="POST" action="{{ route('admin.international-students.reject', $student) }}">@csrf
                                    <textarea name="rejection_reason" rows="2" required class="w-full text-xs rounded-lg border-gray-200 mb-2" placeholder="Rad etish sababi..."></textarea>
                                    <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg font-semibold">Yuborish</button>
                                </form>
                            </div>
                        </div>
                        @endif

                        {{-- Jarayon boshqarish --}}
                        <div class="bg-white shadow-sm rounded-xl p-4 border border-gray-100">
                            <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                                Jarayon
                            </h4>
                            <div class="space-y-3">
                                @foreach([
                                    ['label' => 'Registratsiya', 'status' => $rps, 'type' => 'registration', 'labels' => ['passport_accepted' => 'Pasport olindi', 'registering' => 'Qilinmoqda', 'done' => 'Tugallandi']],
                                    ['label' => 'Viza', 'status' => $vps, 'type' => 'visa', 'labels' => ['passport_accepted' => 'Pasport olindi', 'registering' => 'Yangilanmoqda', 'done' => 'Tugallandi']],
                                ] as $proc)
                                <div class="p-3 rounded-lg {{ $proc['status'] === 'registering' || $proc['status'] === 'passport_accepted' ? 'bg-blue-50 border border-blue-100' : ($proc['status'] === 'done' ? 'bg-green-50 border border-green-100' : 'bg-gray-50 border border-gray-100') }}">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-bold text-gray-700">{{ $proc['label'] }}</span>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ match($proc['status']) { 'passport_accepted' => 'bg-blue-100 text-blue-700', 'registering' => 'bg-yellow-100 text-yellow-700', 'done' => 'bg-green-100 text-green-700', default => 'bg-gray-200 text-gray-500' } }}">{{ $proc['labels'][$proc['status']] ?? 'Kutilmoqda' }}</span>
                                    </div>
                                    @if($proc['status'] === 'none' || $proc['status'] === 'done')
                                        <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $proc['type'] }}"><button type="submit" class="w-full px-3 py-1.5 bg-blue-600 text-white text-[10px] font-semibold rounded-lg hover:bg-blue-700 transition" onclick="return confirm('Pasportni qabul?')">Pasport qabul qilish</button></form>
                                    @elseif($proc['status'] === 'passport_accepted')
                                        <form method="POST" action="{{ route('admin.international-students.mark-registering', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $proc['type'] }}"><button type="submit" class="w-full px-3 py-1.5 bg-yellow-500 text-white text-[10px] font-semibold rounded-lg hover:bg-yellow-600 transition">{{ $proc['type'] === 'visa' ? 'Viza yangilanmoqda' : 'Registratsiya qilinmoqda' }}</button></form>
                                    @elseif($proc['status'] === 'registering')
                                        <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="{{ $proc['type'] }}"><button type="submit" class="w-full px-3 py-1.5 bg-green-600 text-white text-[10px] font-semibold rounded-lg hover:bg-green-700 transition" onclick="return confirm('Yangilandi. Pasport qaytarilsinmi?')">Yangilandi — Pasport qaytarish</button></form>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <form method="POST" action="{{ route('admin.international-students.destroy-visa-info', $student) }}">@csrf @method('DELETE')
                                    <button type="submit" class="text-[10px] text-red-400 hover:text-red-600 font-medium transition" onclick="return confirm('Barcha viza ma\'lumotlarini o\'chirasizmi?')">Ma'lumotlarni o'chirish</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- O'ng: Ma'lumotlar --}}
                    <div class="lg:col-span-8">
                        <div class="bg-white shadow-sm rounded-xl border border-gray-100 overflow-hidden">
                            {{-- Pasport --}}
                            <div class="p-5 border-b border-gray-100">
                                <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                                    Pasport
                                </h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-2">
                                    <div><span class="text-[10px] text-gray-400 uppercase">Raqami</span><br><span class="font-bold text-gray-800 text-sm">{{ $visaInfo->passport_number ?? '-' }}</span></div>
                                    <div><span class="text-[10px] text-gray-400 uppercase">Berilgan joy</span><br><span class="text-sm text-gray-700">{{ $visaInfo->passport_issued_place ?? '-' }}</span></div>
                                    <div><span class="text-[10px] text-gray-400 uppercase">Berilgan sana</span><br><span class="text-sm text-gray-700">{{ $visaInfo->passport_issued_date?->format('d.m.Y') ?? '-' }}</span></div>
                                    <div><span class="text-[10px] text-gray-400 uppercase">Tugash</span><br><span class="text-sm text-gray-700">{{ $visaInfo->passport_expiry_date?->format('d.m.Y') ?? '-' }}</span></div>
                                </div>
                            </div>

                            {{-- Registratsiya + Viza 2 ustunda --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                                {{-- Registratsiya --}}
                                <div class="p-5">
                                    <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Registratsiya</h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between"><span class="text-[10px] text-gray-400">Boshlanish</span><span class="text-sm font-medium text-gray-700">{{ $visaInfo->registration_start_date?->format('d.m.Y') ?? '-' }}</span></div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] text-gray-400">Tugash</span>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-sm font-medium text-gray-700">{{ $visaInfo->registration_end_date?->format('d.m.Y') ?? '-' }}</span>
                                                @if($regDays !== null && $regDays <= 7)
                                                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded {{ $regDays <= 0 ? 'bg-red-600 text-white' : ($regDays <= 3 ? 'bg-red-100 text-red-700' : ($regDays <= 5 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700')) }}">{{ $regDays <= 0 ? 'TUGAGAN' : $regDays.'k' }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex justify-between"><span class="text-[10px] text-gray-400">Kirish sanasi</span><span class="text-sm font-medium text-gray-700">{{ $visaInfo->entry_date?->format('d.m.Y') ?? '-' }}</span></div>
                                    </div>
                                </div>
                                {{-- Viza --}}
                                <div class="p-5">
                                    <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Viza</h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between"><span class="text-[10px] text-gray-400">Raqami / Turi</span><span class="text-sm font-bold text-gray-800">{{ $visaInfo->visa_number ?? '-' }} <span class="font-normal text-gray-500">{{ $visaInfo->visa_type }}</span></span></div>
                                        <div class="flex justify-between"><span class="text-[10px] text-gray-400">Kirishlar / Muddat</span><span class="text-sm text-gray-700">{{ $visaInfo->visa_entries_count ?? '-' }} marta &middot; {{ $visaInfo->visa_stay_days ?? '-' }} kun</span></div>
                                        <div class="flex justify-between"><span class="text-[10px] text-gray-400">Boshlanish</span><span class="text-sm font-medium text-gray-700">{{ $visaInfo->visa_start_date?->format('d.m.Y') ?? '-' }}</span></div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] text-gray-400">Tugash</span>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-sm font-medium text-gray-700">{{ $visaInfo->visa_end_date?->format('d.m.Y') ?? '-' }}</span>
                                                @if($visaDays !== null && $visaDays <= 30)
                                                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded {{ $visaDays <= 0 ? 'bg-red-600 text-white' : ($visaDays <= 15 ? 'bg-red-100 text-red-700' : ($visaDays <= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700')) }}">{{ $visaDays <= 0 ? 'TUGAGAN' : $visaDays.'k' }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex justify-between"><span class="text-[10px] text-gray-400">Berilgan</span><span class="text-sm text-gray-700">{{ $visaInfo->visa_issued_place ?? '-' }}, {{ $visaInfo->visa_issued_date?->format('d.m.Y') ?? '' }}</span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Tug'ilgan joy + Hujjatlar --}}
                            <div class="p-5 border-t border-gray-100">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">Tug'ilgan joy</h4>
                                        <span class="text-sm text-gray-700">{{ $visaInfo->birth_country ?? '-' }}, {{ $visaInfo->birth_region ?? '' }}, {{ $visaInfo->birth_city ?? '' }}</span>
                                    </div>
                                    <div>
                                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2">Hujjatlar</h4>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach(['passport_scan_path' => 'Pasport', 'visa_scan_path' => 'Viza', 'registration_doc_path' => 'Registratsiya'] as $field => $label)
                                                @if($visaInfo->$field)
                                                    <a href="{{ route('admin.international-students.file', [$student, $field]) }}" target="_blank"
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition
                                                       {{ $field === 'passport_scan_path' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200' : ($field === 'visa_scan_path' ? 'bg-green-50 text-green-700 hover:bg-green-100 border border-green-200' : 'bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200') }}">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                        {{ $label }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
