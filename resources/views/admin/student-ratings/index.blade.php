<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Talabalar reytingi
        </h2>
    </x-slot>

    {{-- Filtrlar --}}
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <form method="GET" action="{{ route('admin.student-ratings.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                <select name="department" onchange="this.form.submit()" class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Barcha fakultetlar</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->department_code }}" {{ $selectedDepartment == $dept->department_code ? 'selected' : '' }}>
                            {{ $dept->department_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish</label>
                <select name="specialty" onchange="this.form.submit()" class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Barcha yo'nalishlar</option>
                    @foreach($specialties as $spec)
                        <option value="{{ $spec->specialty_code }}" {{ $selectedSpecialty == $spec->specialty_code ? 'selected' : '' }}>
                            {{ $spec->specialty_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-[140px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kurs</label>
                <select name="level" onchange="this.form.submit()" class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Barchasi</option>
                    @foreach(['11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs', '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs'] as $code => $label)
                        <option value="{{ $code }}" {{ $selectedLevel == $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($selectedDepartment || $selectedSpecialty || $selectedLevel)
            <a href="{{ route('admin.student-ratings.index') }}" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">
                Tozalash
            </a>
            @endif
        </form>

        @if($lastUpdated)
        <div class="mt-3 text-xs text-gray-400">
            Oxirgi yangilanish: {{ \Carbon\Carbon::parse($lastUpdated)->format('d.m.Y H:i') }}
            &middot; Jami: {{ $totalStudents }} ta talaba
        </div>
        @endif
    </div>

    @if($top10->isEmpty())
    <div class="bg-white shadow rounded-lg p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
        </svg>
        <p class="text-gray-500 text-lg">Reyting ma'lumotlari topilmadi.</p>
        <p class="text-gray-400 text-sm mt-2">
            <code>php artisan ratings:calculate</code> buyrug'ini ishga tushiring.
        </p>
    </div>
    @else

    {{-- TOP 10 --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
            <span class="text-yellow-500 text-2xl">&#9733;</span> TOP 10
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach($top10 as $index => $r)
            @php
                if ($index === 0) { $border = 'border-yellow-400 bg-yellow-50'; $medal = '🥇'; $medalSize = 'text-4xl'; }
                elseif ($index === 1) { $border = 'border-gray-400 bg-gray-50'; $medal = '🥈'; $medalSize = 'text-3xl'; }
                elseif ($index === 2) { $border = 'border-amber-600 bg-amber-50'; $medal = '🥉'; $medalSize = 'text-3xl'; }
                else { $border = 'border-blue-200 bg-blue-50'; $medal = ''; $medalSize = ''; }

                if ($r->jn_average >= 80) $scoreColor = 'text-green-600';
                elseif ($r->jn_average >= 60) $scoreColor = 'text-yellow-600';
                else $scoreColor = 'text-red-600';
            @endphp
            <div class="border-2 rounded-xl p-4 text-center {{ $border }} {{ $index < 3 ? 'md:col-span-1 lg:col-span-1' : '' }}">
                @if($medal)
                    <div class="{{ $medalSize }} mb-1">{{ $medal }}</div>
                @else
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center mx-auto mb-2 text-sm">{{ $index + 1 }}</div>
                @endif
                <div class="text-2xl font-bold {{ $scoreColor }}">{{ number_format($r->jn_average, 1) }}</div>
                <div class="font-semibold text-gray-800 text-sm mt-1 leading-tight">{{ $r->full_name }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $r->group_name }}</div>
                <div class="text-xs text-gray-400">{{ $r->subjects_count }} fan</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Qolgan talabalar --}}
    @if($others->total() > 0)
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Boshqa talabalar</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">O'rin</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guruh</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Yo'nalish</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Fanlar</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">JN o'rtacha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($others as $r)
                    @php
                        if ($r->jn_average >= 80) $scoreBg = 'bg-green-100 text-green-800';
                        elseif ($r->jn_average >= 60) $scoreBg = 'bg-yellow-100 text-yellow-800';
                        else $scoreBg = 'bg-red-100 text-red-800';
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-500">{{ $r->rank }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $r->full_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $r->group_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 max-w-[200px] truncate">{{ $r->specialty_name }}</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">{{ $r->subjects_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-bold {{ $scoreBg }}">
                                {{ number_format($r->jn_average, 1) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $others->links() }}
        </div>
    </div>
    @endif

    @endif
</x-app-layout>
