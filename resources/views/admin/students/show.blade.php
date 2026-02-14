<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ $student->full_name }}
            </h2>
            <a href="{{ route('admin.students.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Talabalar ro'yxatiga qaytish</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex flex-col md:flex-row">
                        <!-- Rasm va asosiy ma'lumot -->
                        <div class="md:w-1/4 pr-6 flex flex-col items-center">
                            @if($student->image)
                                <img src="{{ $student->image }}" alt="{{ $student->full_name }}"
                                     class="w-40 h-40 object-cover rounded-full shadow-md mb-4"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-40 h-40 rounded-full bg-gray-200 flex items-center justify-center shadow-md mb-4" style="display:none;">
                                    <span class="text-3xl font-bold text-gray-500">{{ strtoupper(substr($student->full_name, 0, 2)) }}</span>
                                </div>
                            @else
                                <div class="w-40 h-40 rounded-full bg-gray-200 flex items-center justify-center shadow-md mb-4">
                                    <span class="text-3xl font-bold text-gray-500">{{ strtoupper(substr($student->full_name, 0, 2)) }}</span>
                                </div>
                            @endif
                            <h3 class="text-lg font-bold text-center mb-1">{{ $student->full_name }}</h3>
                            <p class="text-gray-500 text-sm text-center mb-1">{{ $student->student_id_number }}</p>
                            <p class="text-gray-400 text-xs text-center mb-4">HEMIS ID: {{ $student->hemis_id }}</p>

                            <div class="w-full space-y-2">
                                <a href="{{ route('admin.student-performances.index', $student->hemis_id) }}"
                                   class="block w-full text-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                                    Qayta topshirishlar
                                </a>
                                <a href="{{ route('admin.students.grades', $student->hemis_id) }}"
                                   class="block w-full text-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                                    Baholar
                                </a>
                                <a href="{{ route('admin.students.attendance', $student->hemis_id) }}"
                                   class="block w-full text-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                                    Davomat
                                </a>
                            </div>
                        </div>

                        <!-- Batafsil ma'lumotlar -->
                        <div class="md:w-3/4 mt-6 md:mt-0">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Shaxsiy ma'lumotlar -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">Shaxsiy ma'lumotlar</h4>
                                    <ul class="space-y-2 text-sm">
                                        <li><span class="font-medium text-gray-600">To'liq ism:</span> {{ $student->full_name }}</li>
                                        <li><span class="font-medium text-gray-600">Tug'ilgan sana:</span> {{ $student->birth_date ? $student->birth_date->format('d.m.Y') : '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Jinsi:</span> {{ $student->gender_name ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Telefon:</span> {{ $student->phone ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Telegram:</span> {{ $student->telegram_username ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Fuqaroligi:</span> {{ $student->citizenship_name ?? '-' }}</li>
                                    </ul>
                                </div>

                                <!-- Ta'lim ma'lumotlari -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">Ta'lim ma'lumotlari</h4>
                                    <ul class="space-y-2 text-sm">
                                        <li><span class="font-medium text-gray-600">Fakultet:</span> {{ $student->department_name }}</li>
                                        <li><span class="font-medium text-gray-600">Yo'nalish:</span> {{ $student->specialty_name }}</li>
                                        <li><span class="font-medium text-gray-600">Guruh:</span> {{ $student->group_name }}</li>
                                        <li><span class="font-medium text-gray-600">Kurs:</span> {{ $student->level_name }}</li>
                                        <li><span class="font-medium text-gray-600">Semestr:</span> {{ $student->semester_name }}</li>
                                        <li><span class="font-medium text-gray-600">O'quv rejasi:</span> {{ $student->curriculum->name ?? '-' }}</li>
                                    </ul>
                                </div>

                                <!-- Ta'lim turi va shakli -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">Ta'lim turi va shakli</h4>
                                    <ul class="space-y-2 text-sm">
                                        <li><span class="font-medium text-gray-600">Ta'lim turi:</span> {{ $student->education_type_name ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Ta'lim shakli:</span> {{ $student->education_form_name ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">To'lov shakli:</span> {{ $student->payment_form_name ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Talaba turi:</span> {{ $student->student_type_name ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Talaba holati:</span> {{ $student->student_status_name ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Ijtimoiy kategoriya:</span> {{ $student->social_category_name ?? '-' }}</li>
                                    </ul>
                                </div>

                                <!-- Akademik ma'lumotlar -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">Akademik ko'rsatkichlar</h4>
                                    <ul class="space-y-2 text-sm">
                                        <li><span class="font-medium text-gray-600">O'rtacha GPA:</span>
                                            <span class="font-bold {{ $student->avg_gpa >= 3.5 ? 'text-green-600' : ($student->avg_gpa >= 2.5 ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ $student->avg_gpa ?? '-' }}
                                            </span>
                                        </li>
                                        <li><span class="font-medium text-gray-600">O'rtacha baho:</span> {{ $student->avg_grade ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Umumiy kredit:</span> {{ $student->total_credit ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">O'quv yili:</span> {{ $student->education_year_name ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Kirgan yili:</span> {{ $student->year_of_enter ?? '-' }}</li>
                                        <li><span class="font-medium text-gray-600">Ta'lim tili:</span> {{ $student->language_name ?? '-' }}</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Manzil -->
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-lg mb-3 text-gray-700">Manzil ma'lumotlari</h4>
                                <ul class="space-y-2 text-sm">
                                    <li><span class="font-medium text-gray-600">Mamlakat:</span> {{ $student->country_name ?? '-' }}</li>
                                    <li><span class="font-medium text-gray-600">Viloyat:</span> {{ $student->province_name ?? '-' }}</li>
                                    <li><span class="font-medium text-gray-600">Tuman:</span> {{ $student->district_name ?? '-' }}</li>
                                    <li><span class="font-medium text-gray-600">Yashash joyi:</span> {{ $student->accommodation_name ?? '-' }}</li>
                                </ul>
                            </div>

                            <!-- Tizim ma'lumotlari -->
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-lg mb-3 text-gray-700">Tizim ma'lumotlari</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <ul class="space-y-2">
                                            <li><span class="font-medium text-gray-600">Universitet:</span> {{ $student->university_name ?? '-' }}</li>
                                            <li><span class="font-medium text-gray-600">HEMIS yaratilgan:</span> {{ $student->hemis_created_at ? $student->hemis_created_at->format('d.m.Y H:i') : '-' }}</li>
                                            <li><span class="font-medium text-gray-600">HEMIS yangilangan:</span> {{ $student->hemis_updated_at ? $student->hemis_updated_at->format('d.m.Y H:i') : '-' }}</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <ul class="space-y-2">
                                            <li><span class="font-medium text-gray-600">Parol tiklangan:</span>
                                                @if($student->local_password_expires_at)
                                                    {{ $student->local_password_expires_at->format('d.m.Y H:i') }}
                                                    @if($student->local_password_expires_at->isFuture())
                                                        <span class="text-green-600">(faol)</span>
                                                    @else
                                                        <span class="text-red-600">(muddati o'tgan)</span>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </li>
                                            <li><span class="font-medium text-gray-600">Parol o'zgartirish:</span>
                                                @if($student->must_change_password)
                                                    <span class="text-yellow-600">Talab qilinadi</span>
                                                @else
                                                    <span class="text-green-600">Yo'q</span>
                                                @endif
                                            </li>
                                            <li><span class="font-medium text-gray-600">Telegram tasdiqlangan:</span>
                                                @if($student->isTelegramVerified())
                                                    <span class="text-green-600">Ha ({{ $student->telegram_verified_at->format('d.m.Y') }})</span>
                                                @else
                                                    <span class="text-red-600">Yo'q</span>
                                                @endif
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
