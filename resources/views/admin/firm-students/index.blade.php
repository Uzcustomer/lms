<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Firma talabalari') }} — {{ $firmName }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">

            {{-- Filtrlar --}}
            <div class="bg-white shadow rounded-lg p-4 mb-4 border border-gray-200">
                <form method="GET" action="{{ route('admin.firm-students.index') }}" class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Ism bo\'yicha qidirish') }}</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Ism..."
                                   class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Kurs') }}</label>
                            <select name="level_code" class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">{{ __('Barchasi') }}</option>
                                @for($i = 1; $i <= 6; $i++)
                                    <option value="{{ $i }}" {{ request('level_code') == $i ? 'selected' : '' }}>{{ $i }}-kurs</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Guruh') }}</label>
                            <input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="Guruh nomi..."
                                   class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Holat') }}</label>
                            <select name="data_status" class="w-full text-sm rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">{{ __('Barchasi') }}</option>
                                <option value="approved" {{ request('data_status') === 'approved' ? 'selected' : '' }}>{{ __('Tasdiqlangan') }}</option>
                                <option value="pending" {{ request('data_status') === 'pending' ? 'selected' : '' }}>{{ __('Kutilmoqda') }}</option>
                                <option value="rejected" {{ request('data_status') === 'rejected' ? 'selected' : '' }}>{{ __('Rad etilgan') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            {{ __('Filtrlash') }}
                        </button>
                        <a href="{{ route('admin.firm-students.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
                            {{ __('Tozalash') }}
                        </a>
                    </div>
                </form>
            </div>

            {{-- Talabalar jadvali --}}
            <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Talaba') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Guruh') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Kurs') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Registratsiya tugash') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Viza tugash') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Holat') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($students as $i => $student)
                                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('admin.firm-students.show', $student) }}'">
                                    <td class="px-4 py-3 text-gray-500">{{ $students->firstItem() + $i }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $student->full_name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $student->group_name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $student->level_name }}</td>
                                    <td class="px-4 py-3">
                                        @if($student->visaInfo?->registration_end_date)
                                            @php $regDays = $student->visaInfo->registrationDaysLeft(); @endphp
                                            <span class="text-xs {{ $regDays <= 3 ? 'text-red-600 font-bold' : ($regDays <= 5 ? 'text-yellow-600 font-semibold' : ($regDays <= 7 ? 'text-green-600' : 'text-gray-600')) }}">
                                                {{ $student->visaInfo->registration_end_date->format('d.m.Y') }}
                                                @if($regDays <= 7)
                                                    ({{ $regDays <= 0 ? 'tugagan!' : $regDays . 'k' }})
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($student->visaInfo?->visa_end_date)
                                            @php $visaDays = $student->visaInfo->visaDaysLeft(); @endphp
                                            <span class="text-xs {{ $visaDays <= 15 ? 'text-red-600 font-bold' : ($visaDays <= 20 ? 'text-yellow-600 font-semibold' : ($visaDays <= 30 ? 'text-green-600' : 'text-gray-600')) }}">
                                                {{ $student->visaInfo->visa_end_date->format('d.m.Y') }}
                                                @if($visaDays <= 30)
                                                    ({{ $visaDays <= 0 ? 'tugagan!' : $visaDays . 'k' }})
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($student->visaInfo)
                                            @if($student->visaInfo->status === 'approved')
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">{{ __('Tasdiqlangan') }}</span>
                                            @elseif($student->visaInfo->status === 'rejected')
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">{{ __('Rad etilgan') }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700">{{ __('Kutilmoqda') }}</span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">Kiritilmagan</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('Talabalar topilmadi') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $students->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
