<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.international-students.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
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
                {{-- Talaba info + ma'lumot kiritilmagan --}}
                <div class="bg-white shadow rounded-lg p-5 border border-gray-200">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
                        <div><span class="text-xs text-gray-400">F.I.Sh</span><br><b>{{ $student->full_name }}</b></div>
                        <div><span class="text-xs text-gray-400">Guruh</span><br>{{ $student->group_name }}</div>
                        <div><span class="text-xs text-gray-400">Kurs</span><br>{{ $student->level_name }}</div>
                        <div><span class="text-xs text-gray-400">Davlat</span><br>{{ $student->country_name ?? '-' }}</div>
                    </div>
                    <div class="text-center py-6 text-yellow-600">
                        <svg class="w-10 h-10 mx-auto mb-2 text-yellow-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        <p class="text-sm font-medium">Talaba hali viza ma'lumotlarini kiritmagan.</p>
                    </div>
                </div>
            @else
                @php
                    $rps = $visaInfo->registration_process_status ?? 'none';
                    $vps = $visaInfo->visa_process_status ?? 'none';
                    $regDays = $visaInfo->registrationDaysLeft();
                    $visaDays = $visaInfo->visaDaysLeft();
                @endphp

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {{-- Chap ustun: talaba info + jarayon --}}
                    <div class="lg:col-span-1 space-y-4">
                        {{-- Talaba --}}
                        <div class="bg-white shadow rounded-lg p-4 border border-gray-200 text-sm">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Talaba</h4>
                            <div class="space-y-2">
                                <div><span class="text-xs text-gray-400">F.I.Sh</span><br><b class="text-gray-800">{{ $student->full_name }}</b></div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><span class="text-xs text-gray-400">Guruh</span><br>{{ $student->group_name }}</div>
                                    <div><span class="text-xs text-gray-400">Kurs</span><br>{{ $student->level_name }}</div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><span class="text-xs text-gray-400">Davlat</span><br>{{ $student->country_name ?? '-' }}</div>
                                    <div><span class="text-xs text-gray-400">Fuqaroligi</span><br>{{ $student->citizenship_name ?? '-' }}</div>
                                </div>
                                <div><span class="text-xs text-gray-400">Fakultet</span><br>{{ $student->department_name }}</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><span class="text-xs text-gray-400">Telefon</span><br>{{ $student->phone ?? '-' }}</div>
                                    <div><span class="text-xs text-gray-400">Firma</span><br><b>{{ $visaInfo->firm_display }}</b></div>
                                </div>
                            </div>
                        </div>

                        {{-- Holat + amallar --}}
                        <div class="bg-white shadow rounded-lg p-4 border border-gray-200 text-sm" x-data="{ showReject: false }">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide">Holat</h4>
                                @if($visaInfo->status === 'approved')
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">Tasdiqlangan</span>
                                @elseif($visaInfo->status === 'rejected')
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Rad etilgan</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">Tekshirilmoqda</span>
                                @endif
                            </div>
                            @if($visaInfo->rejection_reason)
                                <div class="p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700 mb-3">Sabab: {{ $visaInfo->rejection_reason }}</div>
                            @endif
                            @if($visaInfo->status === 'pending')
                            <div class="flex gap-2 mb-3">
                                <form method="POST" action="{{ route('admin.international-students.approve', $student) }}"><@csrf><button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700" onclick="return confirm('Tasdiqlaysizmi?')">Tasdiqlash</button></form>
                                <button type="button" @click="showReject=!showReject" class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700">Rad etish</button>
                            </div>
                            <div x-show="showReject" x-transition class="mb-3">
                                <form method="POST" action="{{ route('admin.international-students.reject', $student) }}">@csrf
                                    <textarea name="rejection_reason" rows="2" required class="w-full text-xs rounded border-gray-300 mb-2" placeholder="Sabab..."></textarea>
                                    <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg">Rad etish</button>
                                </form>
                            </div>
                            @endif

                            {{-- Jarayon --}}
                            <div class="border-t border-gray-100 pt-3 mt-2 space-y-3">
                                {{-- Registratsiya --}}
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-semibold text-gray-600">Registratsiya</span>
                                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ match($rps) { 'passport_accepted' => 'bg-blue-100 text-blue-700', 'registering' => 'bg-yellow-100 text-yellow-700', 'done' => 'bg-green-100 text-green-700', default => 'bg-gray-100 text-gray-500' } }}">{{ match($rps) { 'passport_accepted' => 'Pasport olindi', 'registering' => 'Qilinmoqda', 'done' => 'Tugallandi', default => 'Kutilmoqda' } }}</span>
                                    </div>
                                    <div class="flex gap-1">
                                        @if($rps === 'none' || $rps === 'done')
                                            <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="registration"><button type="submit" class="px-2 py-1 bg-blue-600 text-white text-[10px] font-medium rounded hover:bg-blue-700" onclick="return confirm('Pasportni qabul?')">Pasport olish</button></form>
                                        @elseif($rps === 'passport_accepted')
                                            <form method="POST" action="{{ route('admin.international-students.mark-registering', $student) }}">@csrf<input type="hidden" name="process_type" value="registration"><button type="submit" class="px-2 py-1 bg-yellow-500 text-white text-[10px] font-medium rounded">Qilinmoqda</button></form>
                                        @elseif($rps === 'registering')
                                            <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="registration"><button type="submit" class="px-2 py-1 bg-green-600 text-white text-[10px] font-medium rounded" onclick="return confirm('Pasport qaytarish?')">Yangilandi</button></form>
                                        @endif
                                    </div>
                                </div>
                                {{-- Viza --}}
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-semibold text-gray-600">Viza</span>
                                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ match($vps) { 'passport_accepted' => 'bg-blue-100 text-blue-700', 'registering' => 'bg-yellow-100 text-yellow-700', 'done' => 'bg-green-100 text-green-700', default => 'bg-gray-100 text-gray-500' } }}">{{ match($vps) { 'passport_accepted' => 'Pasport olindi', 'registering' => 'Yangilanmoqda', 'done' => 'Tugallandi', default => 'Kutilmoqda' } }}</span>
                                    </div>
                                    <div class="flex gap-1">
                                        @if($vps === 'none' || $vps === 'done')
                                            <form method="POST" action="{{ route('admin.international-students.accept-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="visa"><button type="submit" class="px-2 py-1 bg-blue-600 text-white text-[10px] font-medium rounded hover:bg-blue-700" onclick="return confirm('Pasportni qabul? Reg. ham birga.')">Pasport olish</button></form>
                                        @elseif($vps === 'passport_accepted')
                                            <form method="POST" action="{{ route('admin.international-students.mark-registering', $student) }}">@csrf<input type="hidden" name="process_type" value="visa"><button type="submit" class="px-2 py-1 bg-yellow-500 text-white text-[10px] font-medium rounded">Yangilanmoqda</button></form>
                                        @elseif($vps === 'registering')
                                            <form method="POST" action="{{ route('admin.international-students.return-passport', $student) }}">@csrf<input type="hidden" name="process_type" value="visa"><button type="submit" class="px-2 py-1 bg-green-600 text-white text-[10px] font-medium rounded" onclick="return confirm('Pasport qaytarish?')">Yangilandi</button></form>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- O'chirish --}}
                            <div class="border-t border-gray-100 pt-3 mt-3">
                                <form method="POST" action="{{ route('admin.international-students.destroy-visa-info', $student) }}" class="inline">@csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium" onclick="return confirm('Barcha viza ma\'lumotlarini o\'chirasizmi?')">Ma'lumotlarni o'chirish</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- O'ng ustun: barcha ma'lumotlar --}}
                    <div class="lg:col-span-2">
                        <div class="bg-white shadow rounded-lg p-5 border border-gray-200 text-sm">
                            {{-- Pasport --}}
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Pasport</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                <div><span class="text-xs text-gray-400">Raqami</span><br><b>{{ $visaInfo->passport_number ?? '-' }}</b></div>
                                <div><span class="text-xs text-gray-400">Berilgan joy</span><br>{{ $visaInfo->passport_issued_place ?? '-' }}</div>
                                <div><span class="text-xs text-gray-400">Berilgan sana</span><br>{{ $visaInfo->passport_issued_date?->format('d.m.Y') ?? '-' }}</div>
                                <div><span class="text-xs text-gray-400">Tugash sanasi</span><br>{{ $visaInfo->passport_expiry_date?->format('d.m.Y') ?? '-' }}</div>
                            </div>

                            {{-- Registratsiya --}}
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3 border-t border-gray-100 pt-4">Registratsiya</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                                <div><span class="text-xs text-gray-400">Boshlanish</span><br>{{ $visaInfo->registration_start_date?->format('d.m.Y') ?? '-' }}</div>
                                <div>
                                    <span class="text-xs text-gray-400">Tugash</span><br>{{ $visaInfo->registration_end_date?->format('d.m.Y') ?? '-' }}
                                    @if($regDays !== null && $regDays <= 7)
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded {{ $regDays <= 0 ? 'bg-red-600 text-white' : ($regDays <= 3 ? 'bg-red-100 text-red-700' : ($regDays <= 5 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700')) }}">{{ $regDays <= 0 ? 'TUGAGAN' : $regDays.'k' }}</span>
                                    @endif
                                </div>
                                <div><span class="text-xs text-gray-400">Kirish sanasi</span><br>{{ $visaInfo->entry_date?->format('d.m.Y') ?? '-' }}</div>
                            </div>

                            {{-- Viza --}}
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3 border-t border-gray-100 pt-4">Viza</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                <div><span class="text-xs text-gray-400">Raqami</span><br><b>{{ $visaInfo->visa_number ?? '-' }}</b></div>
                                <div><span class="text-xs text-gray-400">Turi</span><br>{{ $visaInfo->visa_type ?? '-' }}</div>
                                <div><span class="text-xs text-gray-400">Kirishlar</span><br>{{ $visaInfo->visa_entries_count ?? '-' }}</div>
                                <div><span class="text-xs text-gray-400">Muddat</span><br>{{ $visaInfo->visa_stay_days ?? '-' }} kun</div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                <div><span class="text-xs text-gray-400">Boshlanish</span><br>{{ $visaInfo->visa_start_date?->format('d.m.Y') ?? '-' }}</div>
                                <div>
                                    <span class="text-xs text-gray-400">Tugash</span><br>{{ $visaInfo->visa_end_date?->format('d.m.Y') ?? '-' }}
                                    @if($visaDays !== null && $visaDays <= 30)
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded {{ $visaDays <= 0 ? 'bg-red-600 text-white' : ($visaDays <= 15 ? 'bg-red-100 text-red-700' : ($visaDays <= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700')) }}">{{ $visaDays <= 0 ? 'TUGAGAN' : $visaDays.'k' }}</span>
                                    @endif
                                </div>
                                <div><span class="text-xs text-gray-400">Berilgan joy</span><br>{{ $visaInfo->visa_issued_place ?? '-' }}</div>
                                <div><span class="text-xs text-gray-400">Berilgan sana</span><br>{{ $visaInfo->visa_issued_date?->format('d.m.Y') ?? '-' }}</div>
                            </div>

                            {{-- Tug'ilgan joy --}}
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3 border-t border-gray-100 pt-4">Tug'ilgan joy</h4>
                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div><span class="text-xs text-gray-400">Davlat</span><br>{{ $visaInfo->birth_country ?? '-' }}</div>
                                <div><span class="text-xs text-gray-400">Viloyat</span><br>{{ $visaInfo->birth_region ?? '-' }}</div>
                                <div><span class="text-xs text-gray-400">Shahar</span><br>{{ $visaInfo->birth_city ?? '-' }}</div>
                            </div>

                            {{-- Hujjatlar --}}
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3 border-t border-gray-100 pt-4">Hujjatlar</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach([
                                    'passport_scan_path' => 'Pasport',
                                    'visa_scan_path' => 'Viza',
                                    'registration_doc_path' => 'Registratsiya',
                                ] as $field => $label)
                                    @if($visaInfo->$field)
                                        <a href="{{ route('admin.international-students.file', [$student, $field]) }}" target="_blank"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 rounded border border-gray-200 hover:bg-gray-100 text-xs text-gray-700 transition">
                                            <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                            {{ $label }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
