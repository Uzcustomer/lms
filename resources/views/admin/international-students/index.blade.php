<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
                    </svg>
                </div>
                <h2 class="font-semibold text-sm text-gray-800 leading-tight">
                    {{ __('Xalqaro talabalar') }}
                </h2>
            </div>
            <a href="{{ route('admin.international-students.export', request()->all()) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Excel export
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 px-3">
            @if(session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Statistika kartochkalar --}}
            @php
                $totalStudents = $students->total();
                $filledCount = \App\Models\StudentVisaInfo::whereIn('student_id',
                    \App\Models\Student::where('department_name', 'like', '%alqaro%')->pluck('id')
                )->count();
                $approvedCount = \App\Models\StudentVisaInfo::whereIn('student_id',
                    \App\Models\Student::where('department_name', 'like', '%alqaro%')->pluck('id')
                )->where('status', 'approved')->count();
                $pendingCount = \App\Models\StudentVisaInfo::whereIn('student_id',
                    \App\Models\Student::where('department_name', 'like', '%alqaro%')->pluck('id')
                )->where('status', 'pending')->count();
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Jami talabalar</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalStudents }}</p>
                        </div>
                        <div class="p-2.5 bg-blue-50 rounded-xl">
                            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Ma'lumot kiritgan</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $filledCount }}</p>
                        </div>
                        <div class="p-2.5 bg-green-50 rounded-xl">
                            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Tasdiqlangan</p>
                            <p class="text-2xl font-bold text-green-600 mt-1">{{ $approvedCount }}</p>
                        </div>
                        <div class="p-2.5 bg-green-50 rounded-xl">
                            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500 font-medium">Kutilmoqda</p>
                            <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $pendingCount }}</p>
                        </div>
                        <div class="p-2.5 bg-yellow-50 rounded-xl">
                            <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filtrlar --}}
            <div class="bg-white shadow-sm rounded-xl p-4 mb-4 border border-gray-200" x-data="{ showFilters: {{ request()->hasAny(['search','level_code','group_name','firm','data_status','visa_expiry','registration_expiry']) ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between mb-2">
                    <button @click="showFilters = !showFilters" type="button" class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-indigo-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/>
                        </svg>
                        Filtrlar
                        <svg class="w-3 h-3 transition-transform" :class="showFilters ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>
                    @if(request()->hasAny(['search','level_code','group_name','firm','data_status','visa_expiry','registration_expiry']))
                        <a href="{{ route('admin.international-students.index') }}" class="text-xs text-red-500 hover:text-red-700 font-medium">Filtrlarni tozalash</a>
                    @endif
                </div>
                <form method="GET" action="{{ route('admin.international-students.index') }}" x-show="showFilters" x-transition class="space-y-3 pt-2 border-t border-gray-100">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Ism</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Qidirish..."
                                   class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Kurs</label>
                            <select name="level_code" class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Barchasi</option>
                                @for($i = 1; $i <= 6; $i++)
                                    <option value="{{ $i }}" {{ request('level_code') == $i ? 'selected' : '' }}>{{ $i }}-kurs</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Guruh</label>
                            <input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="Guruh..."
                                   class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Firma</label>
                            <select name="firm" class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Barchasi</option>
                                @foreach($firms as $key => $label)
                                    <option value="{{ $key }}" {{ request('firm') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                                <option value="other" {{ request('firm') === 'other' ? 'selected' : '' }}>Boshqa</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Holat</label>
                            <select name="data_status" class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Barchasi</option>
                                <option value="filled" {{ request('data_status') === 'filled' ? 'selected' : '' }}>Kiritilgan</option>
                                <option value="not_filled" {{ request('data_status') === 'not_filled' ? 'selected' : '' }}>Kiritilmagan</option>
                                <option value="approved" {{ request('data_status') === 'approved' ? 'selected' : '' }}>Tasdiqlangan</option>
                                <option value="pending" {{ request('data_status') === 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                                <option value="rejected" {{ request('data_status') === 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Viza tugash</label>
                            <select name="visa_expiry" class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Barchasi</option>
                                <option value="15" {{ request('visa_expiry') == '15' ? 'selected' : '' }}>15 kun</option>
                                <option value="20" {{ request('visa_expiry') == '20' ? 'selected' : '' }}>20 kun</option>
                                <option value="30" {{ request('visa_expiry') == '30' ? 'selected' : '' }}>30 kun</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Propiska tugash</label>
                            <select name="registration_expiry" class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Barchasi</option>
                                <option value="3" {{ request('registration_expiry') == '3' ? 'selected' : '' }}>3 kun</option>
                                <option value="5" {{ request('registration_expiry') == '5' ? 'selected' : '' }}>5 kun</option>
                                <option value="7" {{ request('registration_expiry') == '7' ? 'selected' : '' }}>7 kun</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
                            Filtrlash
                        </button>
                    </div>
                </form>
            </div>

            {{-- Talabalar jadvali --}}
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-slate-50 to-gray-50">
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">#</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Talaba</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Guruh</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Kurs</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Ma'lumot</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Propiska</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Viza</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Firma</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Holat</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Pasport</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($students as $i => $student)
                                @php
                                    $visa = $student->visaInfo;
                                    $regDays = $visa?->registrationDaysLeft();
                                    $visaDays = $visa?->visaDaysLeft();
                                    $hasUrgent = ($regDays !== null && $regDays <= 3) || ($visaDays !== null && $visaDays <= 15);
                                @endphp
                                <tr class="hover:bg-indigo-50/40 cursor-pointer transition {{ $hasUrgent ? 'bg-red-50/30' : '' }}"
                                    onclick="window.location='{{ route('admin.international-students.show', $student) }}'">
                                    <td class="px-3 py-3 text-gray-400 text-xs">{{ $students->firstItem() + $i }}</td>
                                    <td class="px-3 py-3">
                                        <span class="font-medium text-gray-900">{{ $student->full_name }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600">{{ $student->group_name }}</td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full bg-slate-100 text-slate-600">{{ $student->level_code }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($visa)
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                                <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Ha
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">
                                                <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                Yo'q
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($visa?->registration_end_date)
                                            <div class="text-xs {{ $regDays <= 3 ? 'text-red-600 font-bold' : ($regDays <= 5 ? 'text-yellow-600 font-semibold' : ($regDays <= 7 ? 'text-green-600 font-medium' : 'text-gray-600')) }}">
                                                {{ $visa->registration_end_date->format('d.m.Y') }}
                                            </div>
                                            @if($regDays !== null && $regDays <= 7)
                                                <span class="inline-flex items-center px-1.5 py-0.5 mt-0.5 text-[10px] font-bold rounded {{ $regDays <= 3 ? 'bg-red-100 text-red-700' : ($regDays <= 5 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                                    {{ $regDays <= 0 ? 'TUGAGAN' : $regDays . ' kun' }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($visa?->visa_end_date)
                                            <div class="text-xs {{ $visaDays <= 15 ? 'text-red-600 font-bold' : ($visaDays <= 20 ? 'text-yellow-600 font-semibold' : ($visaDays <= 30 ? 'text-green-600 font-medium' : 'text-gray-600')) }}">
                                                {{ $visa->visa_end_date->format('d.m.Y') }}
                                            </div>
                                            @if($visaDays !== null && $visaDays <= 30)
                                                <span class="inline-flex items-center px-1.5 py-0.5 mt-0.5 text-[10px] font-bold rounded {{ $visaDays <= 15 ? 'bg-red-100 text-red-700' : ($visaDays <= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                                    {{ $visaDays <= 0 ? 'TUGAGAN' : $visaDays . ' kun' }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-600">
                                        {{ $visa?->firm_display ?? '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($visa)
                                            @if($visa->status === 'approved')
                                                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-green-100 text-green-700">Tasdiqlangan</span>
                                            @elseif($visa->status === 'rejected')
                                                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-red-100 text-red-700">Rad etilgan</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-yellow-100 text-yellow-700">Kutilmoqda</span>
                                            @endif
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if($visa)
                                            @if($visa->passport_handed_over)
                                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100">
                                                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-100">
                                                    <svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-12 text-center">
                                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                                        </svg>
                                        <p class="text-sm text-gray-500">Talabalar topilmadi</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($students->hasPages())
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $students->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
