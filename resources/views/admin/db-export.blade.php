<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">DB ma'lumotlar</h2>
    </x-slot>

    <div class="py-6" x-data="{ timingOpen: {{ $errors->any() ? 'true' : 'false' }} }" @keydown.escape.window="timingOpen = false">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Ma'lumotlar bazasidan Excel eksport</h3>
                <p class="text-sm text-gray-500 mb-6">Kerakli jadvalni tanlang va Excel formatida yuklab oling.</p>

                @if(session('error') || $errors->any())
                    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                        {{ session('error') ?: $errors->first() }}
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <a href="{{ route('admin.export.curriculum-subjects') }}"
                       class="flex flex-col items-center p-5 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition">
                        <svg class="w-10 h-10 text-blue-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Curriculum Subjects</span>
                        <span class="text-xs text-gray-500 mt-1">curriculum_subjects jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.semesters') }}"
                       class="flex flex-col items-center p-5 bg-green-50 border border-green-200 rounded-xl hover:bg-green-100 transition">
                        <svg class="w-10 h-10 text-green-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Semesters</span>
                        <span class="text-xs text-gray-500 mt-1">semesters jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.curricula') }}"
                       class="flex flex-col items-center p-5 bg-purple-50 border border-purple-200 rounded-xl hover:bg-purple-100 transition">
                        <svg class="w-10 h-10 text-purple-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Curricula</span>
                        <span class="text-xs text-gray-500 mt-1">curricula jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.groups') }}"
                       class="flex flex-col items-center p-5 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition">
                        <svg class="w-10 h-10 text-rose-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Guruhlar ro'yxati</span>
                        <span class="text-xs text-gray-500 mt-1">groups jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.lesson-pairs') }}"
                       class="flex flex-col items-center p-5 bg-cyan-50 border border-cyan-200 rounded-xl hover:bg-cyan-100 transition">
                        <svg class="w-10 h-10 text-cyan-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Juftliklar (dars soatlari)</span>
                        <span class="text-xs text-gray-500 mt-1">kodi, nomi va vaqti — schedules jadvalidan</span>
                    </a>

                    {{-- Baho qo'yish vaqti: tugma modal ochadi, filtrlar o'sha yerda --}}
                    <button type="button" @click="timingOpen = true"
                            class="flex flex-col items-center p-5 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition text-left">
                        <svg class="w-10 h-10 text-indigo-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Baho qo'yish vaqti</span>
                        <span class="text-xs text-gray-500 mt-1 text-center">o'qituvchilar kesimida · sana, guruh, semestr, o'qituvchi bo'yicha · faqat bakalavr</span>
                        <span class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Hisoblash
                        </span>
                    </button>

                    <a href="{{ route('admin.export.teachers') }}"
                       class="flex flex-col items-center p-5 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition">
                        <svg class="w-10 h-10 text-blue-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Xodimlar</span>
                        <span class="text-xs text-gray-500 mt-1">teachers jadvali — HEMIS id bilan</span>
                    </a>

                    <a href="{{ route('admin.tutors.index') }}"
                       class="flex flex-col items-center p-5 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition">
                        <svg class="w-10 h-10 text-amber-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Tyutorlar ro'yxati</span>
                        <span class="text-xs text-gray-500 mt-1">Har bir tyutor guruhlarini Excelga yuklab olish</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Baho qo'yish vaqti — filtrlar modali --}}
        <div x-show="timingOpen" x-cloak style="display:none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
            <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden" @click.outside="timingOpen = false">
                <div class="flex items-center gap-3 px-5 py-4 bg-indigo-600 text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <div class="text-base font-bold">Baho qo'yish vaqti</div>
                        <div class="text-xs text-indigo-100">Dars vaqtida / ish vaqtida / 18:00 dan keyin / necha kun keyin · faqat bakalavr</div>
                    </div>
                    <button type="button" @click="timingOpen = false" class="ml-auto text-2xl leading-none text-indigo-100 hover:text-white">&times;</button>
                </div>

                <form method="GET" action="{{ route('admin.export.teacher-grade-timing') }}" class="px-5 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Sanadan <span class="text-red-500">*</span></label>
                            <input type="date" name="date_from" required
                                   value="{{ old('date_from', now('Asia/Tashkent')->startOfMonth()->toDateString()) }}"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Sanagacha <span class="text-red-500">*</span></label>
                            <input type="date" name="date_to" required
                                   value="{{ old('date_to', now('Asia/Tashkent')->subDay()->toDateString()) }}"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Guruh</label>
                        <select name="group_hemis_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Barchasi</option>
                            @foreach(($timingGroups ?? []) as $grp)
                                <option value="{{ $grp->group_hemis_id }}" {{ old('group_hemis_id') == $grp->group_hemis_id ? 'selected' : '' }}>{{ $grp->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Semestr</label>
                            <select name="semester_code" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Barchasi</option>
                                @foreach(($timingSemesters ?? []) as $sem)
                                    <option value="{{ $sem->code }}" {{ old('semester_code') == $sem->code ? 'selected' : '' }}>{{ $sem->name }} ({{ $sem->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">O'qituvchi</label>
                            <select name="employee_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Barchasi</option>
                                @foreach(($timingTeachers ?? []) as $tch)
                                    <option value="{{ $tch->hemis_id }}" {{ old('employee_id') == $tch->hemis_id ? 'selected' : '' }}>{{ $tch->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <p class="text-xs text-gray-400">Guruh talabaning hozirgi guruhi bo'yicha, semestr va o'qituvchi baho qatori bo'yicha filtrlanadi. Filtrlar bo'sh qolsa — hammasi.</p>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="timingOpen = false"
                                class="px-4 py-2 text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg">Bekor qilish</button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            Excel yuklab olish
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
