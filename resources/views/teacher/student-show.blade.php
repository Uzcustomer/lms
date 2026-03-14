<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ $student->full_name }}
            </h2>
            <a href="{{ route('teacher.students') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Talabalar ro'yxatiga qaytish</a>
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

                            <!-- Holat badge -->
                            @if($student->student_status_name)
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium mb-4
                                    {{ $student->student_status_code == '10' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $student->student_status_name }}
                                </span>
                            @endif
                        </div>

                        <!-- Batafsil ma'lumotlar -->
                        <div class="md:w-3/4 mt-6 md:mt-0">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                {{-- Shaxsiy ma'lumotlar --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Shaxsiy ma'lumotlar</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">F.I.O</td><td class="py-1 text-gray-900">{{ $student->full_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Tug'ilgan sana</td><td class="py-1 text-gray-900">{{ $student->birth_date ? $student->birth_date->format('d.m.Y') : '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Jinsi</td><td class="py-1 text-gray-900">{{ $student->gender_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Fuqarolik</td><td class="py-1 text-gray-900">{{ $student->citizenship_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Telefon</td><td class="py-1 text-gray-900">{{ $student->phone ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Telegram</td><td class="py-1 text-gray-900">{{ $student->telegram_username ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- Akademik ko'rsatkichlar --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Akademik ko'rsatkichlar</h4>
                                    <table class="w-full text-sm">
                                        <tr>
                                            <td class="py-1 text-gray-500 w-2/5">O'rtacha GPA</td>
                                            <td class="py-1">
                                                <span class="font-bold {{ ($student->avg_gpa ?? 0) >= 3.5 ? 'text-green-600' : (($student->avg_gpa ?? 0) >= 2.5 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $student->avg_gpa ?? '-' }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr><td class="py-1 text-gray-500">O'rtacha baho</td><td class="py-1 text-gray-900">{{ $student->avg_grade ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Jami kredit</td><td class="py-1 text-gray-900">{{ $student->total_credit ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Jami yuklama</td><td class="py-1 text-gray-900">{{ $student->total_acload ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Kirish yili</td><td class="py-1 text-gray-900">{{ $student->year_of_enter ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Bitiruvchi</td><td class="py-1 text-gray-900">{{ $student->is_graduate ? 'Ha' : 'Yo\'q' }}</td></tr>
                                    </table>
                                </div>

                                {{-- Fakultet va yo'nalish --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Fakultet va yo'nalish</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">Fakultet</td><td class="py-1 text-gray-900">{{ $student->department_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Yo'nalish</td><td class="py-1 text-gray-900">{{ $student->specialty_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Guruh</td><td class="py-1 text-gray-900">{{ $student->group_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Kurs</td><td class="py-1 text-gray-900">{{ $student->level_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Semestr</td><td class="py-1 text-gray-900">{{ $student->semester_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- Ta'lim shakli --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Ta'lim shakli</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">Ta'lim turi</td><td class="py-1 text-gray-900">{{ $student->education_type_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Ta'lim shakli</td><td class="py-1 text-gray-900">{{ $student->education_form_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">To'lov shakli</td><td class="py-1 text-gray-900">{{ $student->payment_form_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Talaba turi</td><td class="py-1 text-gray-900">{{ $student->student_type_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Holati</td><td class="py-1 text-gray-900">{{ $student->student_status_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- Registrator ofisi xodimlari --}}
                                @if(isset($frontOffice) || isset($backOffice))
                                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, #eff6ff, #f0fdf4); border: 1px solid #bfdbfe;">
                                    <h4 class="font-semibold text-base mb-3 border-b pb-2" style="color: #1e40af;">Registrator ofisi xodimlari</h4>
                                    <table class="w-full text-sm">
                                        <tr>
                                            <td class="py-1.5 w-2/5" style="color: #2563eb; font-weight: 600;">Front ofis</td>
                                            <td class="py-1.5">
                                                @if($frontOffice)
                                                    <span class="font-bold text-gray-900">{{ $frontOffice->teacher->full_name ?? '-' }}</span>
                                                    @if($frontOffice->started_at)
                                                        <span class="text-gray-400 text-xs ml-1">({{ $frontOffice->started_at->format('d.m.Y') }} dan)</span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">Biriktirilmagan</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1.5 w-2/5" style="color: #b45309; font-weight: 600;">Back ofis</td>
                                            <td class="py-1.5">
                                                @if($backOffice)
                                                    <span class="font-bold text-gray-900">{{ $backOffice->teacher->full_name ?? '-' }}</span>
                                                    @if($backOffice->started_at)
                                                        <span class="text-gray-400 text-xs ml-1">({{ $backOffice->started_at->format('d.m.Y') }} dan)</span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">Biriktirilmagan</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                @endif

                                {{-- Tyutor ma'lumotlari --}}
                                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border: 1px solid #a7f3d0;">
                                    <h4 class="font-semibold text-base mb-3 border-b pb-2" style="color: #065f46;">Tyutor</h4>
                                    <div class="mb-3">
                                        @if(isset($currentTutor) && $currentTutor)
                                            <div class="flex items-center gap-3 p-2 rounded-lg" style="background: #d1fae5;">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #059669, #10b981); color: #fff; font-weight: 700; font-size: 11px;">
                                                    {{ mb_substr($currentTutor->first_name ?? '', 0, 1) }}{{ mb_substr($currentTutor->second_name ?? '', 0, 1) }}
                                                </div>
                                                <div>
                                                    <span class="font-bold text-sm text-gray-900">{{ $currentTutor->full_name }}</span>
                                                    <div class="text-xs text-gray-500">{{ $currentTutor->department ?? '' }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="text-sm text-gray-400 p-2">Tyutor biriktirilmagan</div>
                                        @endif
                                    </div>

                                    @if(isset($tutorHistory) && $tutorHistory->count() > 0)
                                        <div style="border-top: 1px solid #a7f3d0; padding-top: 8px;">
                                            <p class="text-xs font-semibold mb-2" style="color: #065f46;">Tyutor tarixi</p>
                                            @foreach($tutorHistory as $history)
                                                <div class="flex items-start gap-2 py-1.5 text-xs" style="border-bottom: 1px solid #ecfdf5;">
                                                    <div class="flex-shrink-0 mt-0.5">
                                                        @if($history->removed_at)
                                                            <span class="inline-block w-2 h-2 rounded-full" style="background: #d1d5db;"></span>
                                                        @else
                                                            <span class="inline-block w-2 h-2 rounded-full" style="background: #10b981;"></span>
                                                        @endif
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <span class="font-semibold text-gray-900">{{ $history->teacher_name }}</span>
                                                        @if($history->group_name)
                                                            <span class="text-gray-400"> ({{ $history->group_name }})</span>
                                                        @endif
                                                        <div class="text-gray-400">
                                                            {{ $history->assigned_at->format('d.m.Y') }}
                                                            @if($history->removed_at)
                                                                — {{ $history->removed_at->format('d.m.Y') }}
                                                            @else
                                                                — hozirgi
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- Ijtimoiy ma'lumotlar --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Ijtimoiy ma'lumotlar</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">Ijtimoiy toifa</td><td class="py-1 text-gray-900">{{ $student->social_category_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Turar joy</td><td class="py-1 text-gray-900">{{ $student->accommodation_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Xona sheriklari</td><td class="py-1 text-gray-900">{{ $student->roommate_count ?? '-' }}</td></tr>
                                    </table>
                                </div>

                            </div>

                            {{-- Manzil ma'lumotlari --}}
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Manzil</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">Davlat</td><td class="py-1 text-gray-900">{{ $student->country_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Viloyat</td><td class="py-1 text-gray-900">{{ $student->province_name ?? '-' }}</td></tr>
                                    </table>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">Tuman</td><td class="py-1 text-gray-900">{{ $student->district_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Hudud</td><td class="py-1 text-gray-900">{{ $student->terrain_name ?? '-' }}</td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-teacher-app-layout>
