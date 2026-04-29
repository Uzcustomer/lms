<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Qayta o'qish — qabul oynalari</h2>
    </x-slot>

    @php
        $today = \Carbon\Carbon::today();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ── Yangi oyna yaratish ────────────────────────────────── --}}
            <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" class="mb-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="flex items-center justify-between p-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Yangi qabul oynasini yaratish</h3>
                    <button type="button" @click="open = !open"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span x-text="open ? 'Yashirish' : 'Yaratish'"></span>
                    </button>
                </div>

                <div x-show="open" x-cloak x-transition class="p-5">
                    <form method="POST" action="{{ route('admin.retake.periods.store') }}" class="space-y-4">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish <span class="text-red-500">*</span></label>
                                <select name="specialty_id" required
                                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="">— Tanlang —</option>
                                    @foreach($specialties as $specialty)
                                        <option value="{{ $specialty->specialty_hemis_id }}"
                                            {{ old('specialty_id') == $specialty->specialty_hemis_id ? 'selected' : '' }}>
                                            {{ $specialty->name }} ({{ $specialty->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kurs <span class="text-red-500">*</span></label>
                                <select name="course" required
                                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="">— Tanlang —</option>
                                    @for($i = 1; $i <= 6; $i++)
                                        <option value="{{ $i }}" {{ old('course') == $i ? 'selected' : '' }}>
                                            {{ $i }}-kurs
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Semestr <span class="text-red-500">*</span></label>
                                <select name="semester_id" required
                                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="">— Tanlang —</option>
                                    @foreach($semesters as $semester)
                                        <option value="{{ $semester->semester_hemis_id }}"
                                            {{ old('semester_id') == $semester->semester_hemis_id ? 'selected' : '' }}>
                                            {{ $semester->name }}{{ $semester->education_year ? ' ('.$semester->education_year.')' : '' }}
                                            {{ $semester->current ? ' — joriy' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Boshlanish sanasi <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date" required value="{{ old('start_date') }}"
                                       class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tugash sanasi <span class="text-red-500">*</span></label>
                                <input type="date" name="end_date" required value="{{ old('end_date') }}"
                                       class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm" />
                            </div>
                        </div>

                        <div class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span><strong>Eslatma:</strong> Yaratilgach sanalarni o'zgartirib bo'lmaydi (faqat super-admin override). Adolatlilik qoidasi.</span>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false"
                                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                                Bekor qilish
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                                Yaratish va ochish
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ── Filtrlar ───────────────────────────────────────────── --}}
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-gray-200 p-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Holat</label>
                    <select name="state" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm w-40">
                        <option value="">Hammasi</option>
                        <option value="active" {{ ($filters['state'] ?? '') === 'active' ? 'selected' : '' }}>Faol</option>
                        <option value="upcoming" {{ ($filters['state'] ?? '') === 'upcoming' ? 'selected' : '' }}>Bo'ladigan</option>
                        <option value="closed" {{ ($filters['state'] ?? '') === 'closed' ? 'selected' : '' }}>Yopilgan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Yo'nalish</label>
                    <select name="specialty_id" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm w-56">
                        <option value="">Hammasi</option>
                        @foreach($specialties as $specialty)
                            <option value="{{ $specialty->specialty_hemis_id }}"
                                {{ ($filters['specialty_id'] ?? '') == $specialty->specialty_hemis_id ? 'selected' : '' }}>
                                {{ $specialty->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kurs</label>
                    <select name="course" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm w-32">
                        <option value="">Hammasi</option>
                        @for($i = 1; $i <= 6; $i++)
                            <option value="{{ $i }}" {{ ($filters['course'] ?? '') == $i ? 'selected' : '' }}>{{ $i }}-kurs</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Semestr</label>
                    <select name="semester_id" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 text-sm w-56">
                        <option value="">Hammasi</option>
                        @foreach($semesters as $semester)
                            <option value="{{ $semester->semester_hemis_id }}"
                                {{ ($filters['semester_id'] ?? '') == $semester->semester_hemis_id ? 'selected' : '' }}>
                                {{ $semester->name }}{{ $semester->education_year ? ' ('.$semester->education_year.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if(array_filter($filters))
                    <a href="{{ route('admin.retake.periods.index') }}"
                       class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">Tozalash</a>
                @endif
            </form>

            {{-- ── Ro'yxat ────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Yo'nalish</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kurs</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Semestr</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sanalar</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Holat</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Arizalar</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Yaratildi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $specialtyMap = $specialties->keyBy('specialty_hemis_id');
                                $semesterMap = $semesters->keyBy('semester_hemis_id');
                            @endphp
                            @forelse($periods as $period)
                                @php
                                    $specialty = $specialtyMap->get($period->specialty_id);
                                    $semester = $semesterMap->get($period->semester_id);
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $specialty?->name ?? '#'.$period->specialty_id }}</div>
                                        @if($specialty?->code)
                                            <div class="text-xs text-gray-500">{{ $specialty->code }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $period->course }}-kurs</td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $semester?->name ?? '#'.$period->semester_id }}
                                        @if($semester?->education_year)
                                            <div class="text-xs text-gray-500">{{ $semester->education_year }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <div>{{ $period->start_date->format('d.m.Y') }}</div>
                                        <div class="text-xs text-gray-500">→ {{ $period->end_date->format('d.m.Y') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($period->is_active)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Faol — {{ $period->days_left }} kun qoldi
                                            </span>
                                        @elseif($period->is_upcoming)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                Bo'ladi
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                                Yopilgan
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <span class="font-semibold">{{ $period->applications_count ?? 0 }}</span> ta
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        {{ $period->created_at?->format('d.m.Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                        Hozircha qabul oynalari yaratilmagan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($periods->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $periods->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
