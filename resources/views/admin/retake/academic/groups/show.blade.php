<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.retake.academic.groups.index') }}"
               class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $group->name }}</h2>
        </div>
    </x-slot>

    @php
        $canEdit = in_array($group->status?->value, ['forming', 'scheduled'])
            && \Carbon\Carbon::today()->lessThan($group->start_date);
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Asosiy ma'lumotlar --}}
                <div class="lg:col-span-2 space-y-4">

                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-700">Guruh ma'lumotlari</h3>
                            @php $status = $group->status?->value; @endphp
                            <span class="inline-flex px-2 py-1 rounded-md text-xs font-medium
                                @if($status === 'scheduled') bg-blue-100 text-blue-700
                                @elseif($status === 'in_progress') bg-emerald-100 text-emerald-700
                                @elseif($status === 'completed') bg-gray-100 text-gray-600
                                @else bg-amber-100 text-amber-700
                                @endif">
                                @if($status === 'scheduled') Rejalashtirilgan
                                @elseif($status === 'in_progress') Davom etmoqda
                                @elseif($status === 'completed') Tugagan
                                @else Shakllantirilmoqda
                                @endif
                            </span>
                        </div>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500">Fan</dt>
                                <dd class="font-medium text-gray-800">{{ $group->subject_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Semestr (qarzdorlik)</dt>
                                <dd class="text-gray-800">{{ $group->semester_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Boshlanish</dt>
                                <dd class="text-gray-800">{{ $group->start_date->format('d.m.Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Tugash</dt>
                                <dd class="text-gray-800">{{ $group->end_date->format('d.m.Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">O'qituvchi</dt>
                                <dd class="text-gray-800 font-medium">{{ $group->teacher?->full_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Maks. talabalar</dt>
                                <dd class="text-gray-800">{{ $group->max_students ?? "Cheklanmagan" }}</dd>
                            </div>
                        </dl>

                        @if($canEdit)
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <details class="group" x-data>
                                    <summary class="text-sm text-blue-600 hover:text-blue-800 font-medium cursor-pointer list-none">
                                        Sanalarni / o'qituvchini o'zgartirish
                                    </summary>
                                    <form method="POST" action="{{ route('admin.retake.academic.groups.update', $group->id) }}" class="mt-3 space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Boshlanish</label>
                                                <input type="date" name="start_date" required
                                                       value="{{ $group->start_date->toDateString() }}"
                                                       class="w-full rounded-lg border-gray-300 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Tugash</label>
                                                <input type="date" name="end_date" required
                                                       value="{{ $group->end_date->toDateString() }}"
                                                       class="w-full rounded-lg border-gray-300 text-sm" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">O'qituvchi</label>
                                            <select name="teacher_id" required class="w-full rounded-lg border-gray-300 text-sm">
                                                @foreach($teachers as $teacher)
                                                    <option value="{{ $teacher->id }}" {{ $group->teacher_id === $teacher->id ? 'selected' : '' }}>
                                                        {{ $teacher->full_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Maks. talabalar</label>
                                            <input type="number" name="max_students" min="1" max="1000"
                                                   value="{{ $group->max_students }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm" />
                                        </div>
                                        <button type="submit"
                                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                                            Saqlash
                                        </button>
                                    </form>
                                </details>
                            </div>
                        @else
                            <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
                                Guruh boshlangan yoki tugagan — sanalarni va o'qituvchini o'zgartirib bo'lmaydi.
                            </div>
                        @endif
                    </div>

                    {{-- Talabalar ro'yxati --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">
                            Talabalar ({{ $group->applications->count() }})
                        </h3>
                        <div class="divide-y divide-gray-100">
                            @forelse($group->applications as $app)
                                <div class="py-2.5 flex items-center justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-gray-800">{{ $app->student?->full_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $app->student?->group_name }}
                                            @if($app->verification_code)
                                                — <span class="font-mono text-emerald-700">{{ substr($app->verification_code, 0, 8) }}…</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($app->verification_code)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs rounded-md">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            Tasdiqnoma tayyor
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <div class="py-6 text-center text-sm text-gray-500">Talabalar yo'q.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Yon panel: muddat info --}}
                <div class="space-y-4">
                    <div class="bg-blue-50 rounded-xl border border-blue-200 p-5">
                        <h3 class="text-xs font-semibold text-blue-800 uppercase mb-2">Vaqt</h3>
                        @php
                            $today = \Carbon\Carbon::today();
                            $daysToStart = $today->diffInDays($group->start_date, false);
                            $daysToEnd = $today->diffInDays($group->end_date, false);
                        @endphp
                        @if($daysToStart > 0)
                            <p class="text-2xl font-bold text-blue-900">{{ (int) $daysToStart }}</p>
                            <p class="text-sm text-blue-800">kun qoldi (boshlanishigacha)</p>
                        @elseif($daysToEnd > 0)
                            <p class="text-2xl font-bold text-emerald-700">{{ (int) $daysToEnd }}</p>
                            <p class="text-sm text-emerald-700">kun qoldi (tugashigacha)</p>
                        @else
                            <p class="text-2xl font-bold text-gray-700">Tugagan</p>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
