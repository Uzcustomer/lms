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

                            <!-- Holat badge -->
                            @if($student->student_status_name)
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium mb-4
                                    {{ $student->student_status_code == '10' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $student->student_status_name }}
                                </span>
                            @endif

                            <!-- 5 ga da'vogar toggle -->
                            @if($canToggleFive)
                            <form action="{{ route('admin.students.toggle-five-candidate', $student) }}" method="POST" class="w-full mb-4">
                                @csrf
                                @if($student->is_five_candidate)
                                    <div class="w-full p-3 rounded-lg" style="background-color: #fffbeb; border: 2px solid #f59e0b;">
                                        <div class="flex items-center justify-center gap-2 mb-2">
                                            <svg class="w-5 h-5" style="color: #f59e0b;" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                            <span class="text-sm font-bold" style="color: #b45309;">5 ga da'vogar</span>
                                        </div>
                                        <button type="submit" class="w-full px-3 py-1.5 text-white rounded-md text-xs font-semibold transition" style="background-color: #f59e0b;" onmouseover="this.style.backgroundColor='#d97706'" onmouseout="this.style.backgroundColor='#f59e0b'">
                                            Ro'yxatdan chiqarish
                                        </button>
                                    </div>
                                @else
                                    <button type="submit" class="w-full px-4 py-2 text-white rounded-md text-sm font-semibold transition flex items-center justify-center gap-2" style="background-color: #f59e0b;" onmouseover="this.style.backgroundColor='#d97706'" onmouseout="this.style.backgroundColor='#f59e0b'">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                        5 ga da'vogarlikka kiritish
                                    </button>
                                @endif
                            </form>
                            @endif

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

                                {{-- Shaxsiy ma'lumotlar --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Shaxsiy ma'lumotlar</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">full_name</td><td class="py-1 text-gray-900">{{ $student->full_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">short_name</td><td class="py-1 text-gray-900">{{ $student->short_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">first_name</td><td class="py-1 text-gray-900">{{ $student->first_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">second_name</td><td class="py-1 text-gray-900">{{ $student->second_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">third_name</td><td class="py-1 text-gray-900">{{ $student->third_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">student_id_number</td><td class="py-1 text-gray-900">{{ $student->student_id_number ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">birth_date</td><td class="py-1 text-gray-900">{{ $student->birth_date ? $student->birth_date->format('d.m.Y') : '-' }}</td></tr>
                                        <tr>
                                            <td class="py-1 text-gray-500">gender</td>
                                            <td class="py-1 text-gray-900">{{ $student->gender_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->gender_code ?? '-' }})</span></td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 text-gray-500">citizenship</td>
                                            <td class="py-1 text-gray-900">{{ $student->citizenship_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->citizenship_code ?? '-' }})</span></td>
                                        </tr>
                                    </table>
                                </div>

                                {{-- Akademik ko'rsatkichlar --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Akademik ko'rsatkichlar</h4>
                                    <table class="w-full text-sm">
                                        <tr>
                                            <td class="py-1 text-gray-500 w-2/5">avg_gpa</td>
                                            <td class="py-1">
                                                <span class="font-bold {{ ($student->avg_gpa ?? 0) >= 3.5 ? 'text-green-600' : (($student->avg_gpa ?? 0) >= 2.5 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $student->avg_gpa ?? '-' }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr><td class="py-1 text-gray-500">avg_grade</td><td class="py-1 text-gray-900">{{ $student->avg_grade ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">total_credit</td><td class="py-1 text-gray-900">{{ $student->total_credit ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">total_acload</td><td class="py-1 text-gray-900">{{ $student->total_acload ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">year_of_enter</td><td class="py-1 text-gray-900">{{ $student->year_of_enter ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">is_graduate</td><td class="py-1 text-gray-900">{{ $student->is_graduate ? 'Ha' : 'Yo\'q' }}</td></tr>
                                        @if($canToggleFive)
                                        <tr>
                                            <td class="py-1 text-gray-500">5 ga da'vogar</td>
                                            <td class="py-1">
                                                @if($student->is_five_candidate)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold" style="background-color: #fef3c7; color: #b45309;">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                                        Ha
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">Yo'q</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endif
                                        <tr><td class="py-1 text-gray-500">roommate_count</td><td class="py-1 text-gray-900">{{ $student->roommate_count ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- university --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">university</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->university_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->university_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- department --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">department</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">id</td><td class="py-1 text-gray-900">{{ $student->department_id ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->department_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">code</td><td class="py-1 text-gray-900">{{ $student->department_code ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- specialty --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">specialty</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">id</td><td class="py-1 text-gray-900">{{ $student->specialty_id ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->specialty_name ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">code</td><td class="py-1 text-gray-900">{{ $student->specialty_code ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- group --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">group</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">id</td><td class="py-1 text-gray-900">{{ $student->group_id ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->group_name ?? '-' }}</td></tr>
                                        <tr>
                                            <td class="py-1 text-gray-500">educationLang</td>
                                            <td class="py-1 text-gray-900">{{ $student->language_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->language_code ?? '-' }})</span></td>
                                        </tr>
                                    </table>
                                </div>

                                {{-- level --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">level</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->level_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->level_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- semester --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">semester</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">id</td><td class="py-1 text-gray-900">{{ $student->semester_id ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">code</td><td class="py-1 text-gray-900">{{ $student->semester_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->semester_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- educationYear --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">educationYear</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->education_year_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->education_year_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- educationType --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">educationType</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->education_type_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->education_type_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- educationForm --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">educationForm</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->education_form_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->education_form_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- paymentForm --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">paymentForm</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->payment_form_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->payment_form_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- studentType --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">studentType</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->student_type_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->student_type_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- studentStatus --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">studentStatus</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->student_status_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->student_status_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- socialCategory --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">socialCategory</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->social_category_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->social_category_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                                {{-- accommodation --}}
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">accommodation</h4>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">code</td><td class="py-1 text-gray-900">{{ $student->accommodation_code ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">name</td><td class="py-1 text-gray-900">{{ $student->accommodation_name ?? '-' }}</td></tr>
                                    </table>
                                </div>
                            </div>

                            {{-- Manzil ma'lumotlari --}}
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Manzil (country / province / district / terrain)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">country</td><td class="py-1 text-gray-900">{{ $student->country_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->country_code ?? '-' }})</span></td></tr>
                                        <tr><td class="py-1 text-gray-500">province</td><td class="py-1 text-gray-900">{{ $student->province_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->province_code ?? '-' }})</span></td></tr>
                                    </table>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">district</td><td class="py-1 text-gray-900">{{ $student->district_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->district_code ?? '-' }})</span></td></tr>
                                        <tr><td class="py-1 text-gray-500">terrain</td><td class="py-1 text-gray-900">{{ $student->terrain_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->terrain_code ?? '-' }})</span></td></tr>
                                    </table>
                                </div>
                            </div>

                            {{-- _curriculum / hash / timestamps --}}
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-base mb-3 text-gray-700 border-b pb-2">Tizim ma'lumotlari</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">_curriculum</td><td class="py-1 text-gray-900">{{ $student->curriculum_id ?? '-' }} <span class="text-gray-400 text-xs">{{ $student->curriculum->name ?? '' }}</span></td></tr>
                                        <tr><td class="py-1 text-gray-500">hash</td><td class="py-1 text-gray-900 break-all text-xs">{{ $student->hash ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">other</td><td class="py-1 text-gray-900">{{ $student->other ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">created_at</td><td class="py-1 text-gray-900">{{ $student->hemis_created_at ? $student->hemis_created_at->format('d.m.Y H:i:s') : '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">updated_at</td><td class="py-1 text-gray-900">{{ $student->hemis_updated_at ? $student->hemis_updated_at->format('d.m.Y H:i:s') : '-' }}</td></tr>
                                    </table>
                                    <table class="w-full text-sm">
                                        <tr><td class="py-1 text-gray-500 w-2/5">Telefon</td><td class="py-1 text-gray-900">{{ $student->phone ?? '-' }}</td></tr>
                                        <tr><td class="py-1 text-gray-500">Telegram</td><td class="py-1 text-gray-900">{{ $student->telegram_username ?? '-' }}</td></tr>
                                        <tr>
                                            <td class="py-1 text-gray-500">Telegram tasdiqlangan</td>
                                            <td class="py-1">
                                                @if($student->isTelegramVerified())
                                                    <span class="text-green-600">Ha ({{ $student->telegram_verified_at->format('d.m.Y') }})</span>
                                                @else
                                                    <span class="text-red-600">Yo'q</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 text-gray-500">Parol holati</td>
                                            <td class="py-1">
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
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 text-gray-500">must_change_password</td>
                                            <td class="py-1">
                                                @if($student->must_change_password)
                                                    <span class="text-yellow-600">Ha</span>
                                                @else
                                                    <span class="text-green-600">Yo'q</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
