<div class="space-y-6">
    {{-- Dars jadvali sinxronlash --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Dars jadvalini sinxronlash</h3>
            <p class="mt-1 text-sm text-gray-500">Tanlangan vaqt oralig'i uchun jadvallarni HEMIS dan yangilash</p>
        </div>

        <div class="p-6">
            <div class="mb-4 rounded-md bg-blue-50 p-4 border-l-4 border-blue-500">
                <div class="flex">
                    <svg class="h-5 w-5 text-blue-500 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Bu jarayon fon rejimida ishlaydi va bir necha daqiqadan bir necha soatgacha davom etishi mumkin.</li>
                            <li>Tizim yuklamasi kam bo'lgan vaqtda (kechqurun yoki erta tongda) ishlatish tavsiya etiladi.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.synchronize') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Muddatidan boshlab</label>
                        <input type="date" name="start_date" id="start_date"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Muddatigacha</label>
                        <input type="date" name="end_date" id="end_date"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                </div>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-sm">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Jadvallarni yangilash
                </button>
            </form>
        </div>
    </div>

    {{-- HEMIS ma'lumotlarini sinxronlash --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">HEMIS ma'lumotlarini sinxronlash</h3>
            <p class="mt-1 text-sm text-gray-500">HEMIS API dan ma'lumotlarni yuklab, mahalliy bazaga saqlash</p>
        </div>

        <div class="p-6">
            <div class="mb-4 rounded-md bg-yellow-50 p-4 border-l-4 border-yellow-500">
                <div class="flex">
                    <svg class="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div class="text-sm text-yellow-700">
                        Quyidagi sinxronizatsiyalar fon rejimida ishlaydi. Har bir jarayon HEMIS API'dan ma'lumotlarni yuklab, mahalliy bazaga saqlaydi.
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <form method="POST" action="{{ route('admin.synchronize.curricula') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #4f46e5;" onmouseover="this.style.backgroundColor='#4338ca'" onmouseout="this.style.backgroundColor='#4f46e5'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        O'quv rejalar
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.curriculum-subjects') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #9333ea;" onmouseover="this.style.backgroundColor='#7e22ce'" onmouseout="this.style.backgroundColor='#9333ea'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        O'quv reja fanlari
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.groups') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #16a34a;" onmouseover="this.style.backgroundColor='#15803d'" onmouseout="this.style.backgroundColor='#16a34a'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Guruhlar
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.semesters') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #0d9488;" onmouseover="this.style.backgroundColor='#0f766e'" onmouseout="this.style.backgroundColor='#0d9488'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Semestrlar
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.specialties-departments') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #ea580c;" onmouseover="this.style.backgroundColor='#c2410c'" onmouseout="this.style.backgroundColor='#ea580c'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Mutaxassislik/Kafedralar
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.students') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #0891b2;" onmouseover="this.style.backgroundColor='#0e7490'" onmouseout="this.style.backgroundColor='#0891b2'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Talabalar
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.teachers') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #e11d48;" onmouseover="this.style.backgroundColor='#be123c'" onmouseout="this.style.backgroundColor='#e11d48'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        O'qituvchilar
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.attendance-controls') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #7c3aed;" onmouseover="this.style.backgroundColor='#6d28d9'" onmouseout="this.style.backgroundColor='#7c3aed'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        Davomat nazorati
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.synchronize.curriculum-subject-teachers') }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 border border-transparent rounded-md font-medium text-white transition-colors text-sm" style="background-color: #0891b2;" onmouseover="this.style.backgroundColor='#0e7490'" onmouseout="this.style.backgroundColor='#0891b2'">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Fan-o'qituvchi biriktirishlar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
