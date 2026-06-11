<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            O'quv reja to'g'riligi
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded-lg">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Solishtirish --}}
            @php
                $namunaviyList = $curricula->where('type', 'namunaviy');
                $ishchiList = $curricula->where('type', 'ishchi');
            @endphp
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Namunaviy va ishchi rejani solishtirish</h3>
                    <form method="GET" action="{{ route('admin.oquv-reja.compare') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Namunaviy o'quv reja</label>
                            <select name="reference_id" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Tanlang</option>
                                @foreach($namunaviyList as $curriculum)
                                    <option value="{{ $curriculum->id }}">{{ $curriculum->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ishchi o'quv reja</label>
                            <select name="working_id" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Tanlang</option>
                                @foreach($ishchiList as $curriculum)
                                    <option value="{{ $curriculum->id }}">{{ $curriculum->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Solishtirish
                            </button>
                        </div>
                    </form>
                    @if($namunaviyList->isEmpty() || $ishchiList->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">
                            Solishtirish uchun kamida bitta namunaviy va bitta ishchi o'quv reja yuklangan bo'lishi kerak.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Yangi reja yuklash --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Yangi o'quv reja yuklash (Excel)</h3>
                    <form method="POST" action="{{ route('admin.oquv-reja.store') }}" enctype="multipart/form-data"
                          class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reja turi</label>
                            <select name="type" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="namunaviy">Namunaviy o'quv reja</option>
                                <option value="ishchi">Ishchi o'quv reja</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nomi</label>
                            <input type="text" name="name" required value="{{ old('name') }}"
                                   placeholder="Masalan: 5510100 Davolash ishi (2020) namunaviy"
                                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish kodi</label>
                            <input type="text" name="specialty_code" value="{{ old('specialty_code') }}"
                                   placeholder="5510100" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish nomi</label>
                            <input type="text" name="specialty_name" value="{{ old('specialty_name') }}"
                                   placeholder="Davolash ishi" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">O'quv reja yili</label>
                            <input type="text" name="plan_year" value="{{ old('plan_year') }}"
                                   placeholder="2021/2022" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Excel fayl (.xlsx)</label>
                            <input type="file" name="file" required accept=".xlsx,.xls"
                                   class="w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                        </div>
                        <div class="md:col-span-3">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                                Yuklash
                            </button>
                            <span class="ml-3 text-sm text-gray-500">
                                Ustunlar: Fan kodi, Fan nomi, Blok, Kurs (ishchi uchun), Semestr, Umumiy yuklama (soat),
                                Ma'ruza, Amaliy, Laboratoriya, Seminar, Mustaqil ta'lim, Kredit.
                                Bir fan bir nechta semestrda o'tilsa — har semestri alohida qator.
                            </span>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Yuklangan rejalar --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Yuklangan o'quv rejalar</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">#</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Turi</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Nomi</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Yo'nalish</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Reja yili</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Fan qatorlari</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Jami soat</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Jami kredit</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Yuklangan</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Amallar</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @forelse($curricula as $curriculum)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded text-xs font-medium {{ $curriculum->type === 'namunaviy' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                            {{ $curriculum->typeLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('admin.oquv-reja.show', $curriculum) }}" class="text-blue-600 hover:underline">
                                            {{ $curriculum->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">{{ trim($curriculum->specialty_code . ' ' . $curriculum->specialty_name) ?: '—' }}</td>
                                    <td class="px-4 py-2">{{ $curriculum->plan_year ?: '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ $curriculum->subjects_count }}</td>
                                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($curriculum->total_hours ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($curriculum->total_credit ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                                    <td class="px-4 py-2">{{ $curriculum->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-2">
                                        <form method="POST" action="{{ route('admin.oquv-reja.destroy', $curriculum) }}"
                                              onsubmit="return confirm('Ushbu reja va uning barcha fan qatorlari o’chirilsinmi?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-sm">O'chirish</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-6 text-center text-gray-500">
                                        Hozircha o'quv reja yuklanmagan. Yuqoridagi forma orqali Excel fayl yuklang.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
