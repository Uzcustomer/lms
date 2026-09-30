<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __("Starostalar ro'yxati") }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            {{-- Statistika --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg>
                    </span>
                    <div>
                        <div class="text-2xl font-extrabold text-gray-800">{{ $stats['total'] }}</div>
                        <div class="text-xs text-gray-500 font-medium">Jami guruh</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg bg-green-50 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <div class="text-2xl font-extrabold text-green-700">{{ $stats['assigned'] }}</div>
                        <div class="text-xs text-gray-500 font-medium">Starosta belgilangan</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg bg-red-50 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <div class="text-2xl font-extrabold text-red-700">{{ $stats['missing'] }}</div>
                        <div class="text-xs text-gray-500 font-medium">Belgilanmagan</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                {{-- Filtr --}}
                <form method="GET" action="{{ route('admin.starostalar.index') }}" class="p-4 border-b border-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Kafedra</label>
                            <select name="department" class="select2 w-full" style="width:100%;">
                                <option value="">Barchasi</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->department_hemis_id }}" {{ (string)request('department') === (string)$d->department_hemis_id ? 'selected' : '' }}>{{ $d->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Yo'nalish</label>
                            <select name="specialty" class="select2 w-full" style="width:100%;">
                                <option value="">Barchasi</option>
                                @foreach($specialties as $s)
                                    <option value="{{ $s->specialty_hemis_id }}" {{ (string)request('specialty') === (string)$s->specialty_hemis_id ? 'selected' : '' }}>{{ $s->specialty_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Kurs</label>
                            <select name="course" class="select2 w-full" style="width:100%;">
                                <option value="">Barchasi</option>
                                @for($k = 1; $k <= 6; $k++)
                                    <option value="{{ $k }}" {{ (string)request('course') === (string)$k ? 'selected' : '' }}>{{ $k }}-kurs</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Holat</label>
                            <select name="status" class="select2 w-full" style="width:100%;">
                                <option value="">Barchasi</option>
                                <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Belgilangan</option>
                                <option value="missing" {{ request('status') === 'missing' ? 'selected' : '' }}>Belgilanmagan</option>
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Guruh qidirish</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Guruh nomi..."
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-400 focus:ring-blue-300">
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 018 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                            Filtrlash
                        </button>
                        <a href="{{ route('admin.starostalar.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">
                            Tozalash
                        </a>
                        <a href="{{ route('admin.starostalar.export') }}?{{ http_build_query(request()->query()) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 ml-auto">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Excelga yuklash
                        </a>
                    </div>
                </form>

                {{-- Jadval --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                                <th class="px-3 py-3 text-left font-semibold">№</th>
                                <th class="px-3 py-3 text-left font-semibold">Guruh</th>
                                <th class="px-3 py-3 text-left font-semibold">Kafedra</th>
                                <th class="px-3 py-3 text-left font-semibold">Yo'nalish</th>
                                <th class="px-3 py-3 text-center font-semibold">Kurs</th>
                                <th class="px-3 py-3 text-center font-semibold">Talaba</th>
                                <th class="px-3 py-3 text-left font-semibold">Starosta (F.I.SH)</th>
                                <th class="px-3 py-3 text-left font-semibold">Talaba ID</th>
                                <th class="px-3 py-3 text-left font-semibold">Telefon</th>
                                <th class="px-3 py-3 text-center font-semibold">Holat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($rows as $i => $r)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2.5 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2.5 font-semibold text-gray-800 whitespace-nowrap">{{ $r->group }}</td>
                                    <td class="px-3 py-2.5 text-gray-600">{{ $r->department ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-gray-600">{{ $r->specialty ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-center text-gray-700">{{ $r->course ? $r->course.'-kurs' : '—' }}</td>
                                    <td class="px-3 py-2.5 text-center text-gray-700">{{ $r->student_count }}</td>
                                    <td class="px-3 py-2.5 text-gray-800 whitespace-nowrap">{{ $r->starosta ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-gray-600">{{ $r->starosta_id_number ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ $r->starosta_phone ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-center">
                                        @if($r->starosta)
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Belgilangan</span>
                                        @else
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Belgilanmagan</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-3 py-10 text-center text-gray-400">Ma'lumot topilmadi</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
                jQuery('.select2').select2({ width: '100%' });
            }
        })();
    </script>
</x-app-layout>
