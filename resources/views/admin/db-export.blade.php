<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">DB ma'lumotlar</h2>
    </x-slot>

    <div class="py-6">
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

                    {{-- Baholar qachon qo'yilgan: sana oralig'i tanlanadi, faqat bakalavr --}}
                    <form method="GET" action="{{ route('admin.export.teacher-grade-timing') }}"
                          class="flex flex-col items-center p-5 bg-indigo-50 border border-indigo-200 rounded-xl">
                        <svg class="w-10 h-10 text-indigo-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Baho qo'yish vaqti</span>
                        <span class="text-xs text-gray-500 mt-1 text-center">o'qituvchilar kesimida: dars vaqtida / ish vaqtida / 18:00 dan keyin / necha kun keyin · HEMIS va LMS baholari · faqat bakalavr</span>
                        <div class="flex items-center gap-2 mt-3 w-full">
                            <input type="date" name="date_from" required
                                   value="{{ old('date_from', now('Asia/Tashkent')->startOfMonth()->toDateString()) }}"
                                   class="flex-1 min-w-0 rounded-lg border-gray-300 text-xs px-2 py-1.5">
                            <span class="text-xs text-gray-400">—</span>
                            <input type="date" name="date_to" required
                                   value="{{ old('date_to', now('Asia/Tashkent')->subDay()->toDateString()) }}"
                                   class="flex-1 min-w-0 rounded-lg border-gray-300 text-xs px-2 py-1.5">
                        </div>
                        <button type="submit"
                                class="mt-3 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            Excel yuklab olish
                        </button>
                    </form>

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
    </div>
</x-app-layout>
