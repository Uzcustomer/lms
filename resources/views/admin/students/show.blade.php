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
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
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

                                {{-- Firma belgilash (xalqaro talabalar) --}}
                                @if(str_starts_with(strtolower($student->group_name ?? ''), 'xd') || str_contains(strtolower($student->citizenship_name ?? ''), 'orijiy') || (isset($visaInfo) && $visaInfo))
                                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, #eef2ff, #e0e7ff); border: 1px solid #c7d2fe;">
                                    <h4 class="font-semibold text-base mb-3 border-b pb-2" style="color: #4338ca;">Firma biriktirish</h4>
                                    @if(isset($visaInfo) && $visaInfo?->firm)
                                        <div style="margin-bottom:8px;font-size:13px;">Hozirgi firma: <b style="color:#4338ca;">{{ $visaInfo->firm_display }}</b></div>
                                    @endif
                                    <form method="POST" action="{{ route('admin.international-students.assign-firm', $student) }}" style="display:flex;gap:8px;align-items:center;">
                                        @csrf
                                        <select name="firm" style="flex:1;font-size:12px;padding:6px 10px;border:1px solid #c7d2fe;border-radius:8px;outline:none;">
                                            <option value="">Tanlang</option>
                                            @foreach(\App\Models\StudentVisaInfo::FIRM_OPTIONS as $key => $label)
                                                <option value="{{ $key }}" {{ (isset($visaInfo) && $visaInfo?->firm === $key) ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                            <option value="other" {{ (isset($visaInfo) && $visaInfo?->firm === 'other') ? 'selected' : '' }}>Boshqa</option>
                                        </select>
                                        <button type="submit" style="padding:6px 16px;font-size:12px;font-weight:700;color:#fff;background:#4f46e5;border:none;border-radius:8px;cursor:pointer;">Saqlash</button>
                                    </form>
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
                                                    <a href="{{ route('admin.teachers.show', $currentTutor->id) }}" class="font-bold text-sm text-gray-900 hover:text-green-700 transition">
                                                        {{ $currentTutor->full_name }}
                                                    </a>
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

                            {{-- Fayllar (registrator ofisi) --}}
                            @if($canUploadFiles)
                            <div class="mt-6 p-4 rounded-lg" style="background: linear-gradient(135deg, #fef3c7, #fffbeb); border: 1px solid #fbbf24;">
                                <h4 class="font-semibold text-base mb-3 border-b pb-2" style="color: #92400e;">Fayllar</h4>

                                {{-- Fayl yuklash formasi --}}
                                <form action="{{ route('admin.students.files.upload', $student) }}" method="POST" enctype="multipart/form-data" class="mb-4">
                                    @csrf
                                    <div class="flex flex-col gap-3">
                                        <div>
                                            <label style="font-size:12px; font-weight:600; color:#92400e; display:block; margin-bottom:4px;">Fayl nomi</label>
                                            <input type="text" name="file_name" required placeholder="Masalan: Diplom nusxasi"
                                                   style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; box-sizing:border-box;">
                                        </div>
                                        <div>
                                            <label style="font-size:12px; font-weight:600; color:#92400e; display:block; margin-bottom:4px;">Faylni tanlang</label>
                                            <input type="file" name="file" required
                                                   style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; background:#fff; box-sizing:border-box;">
                                            <p style="font-size:11px; color:#9ca3af; margin-top:2px;">Maksimum hajmi: 10 MB</p>
                                        </div>
                                        <div>
                                            <button type="submit"
                                                    style="padding:8px 20px; border:none; border-radius:8px; background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s;"
                                                    onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                                                Yuklash
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                {{-- Yuklangan fayllar ro'yxati --}}
                                @if($studentFiles->count() > 0)
                                <div style="border-top:1px solid #fbbf24; padding-top:12px;">
                                    <p style="font-size:12px; font-weight:700; color:#92400e; margin-bottom:8px;">Yuklangan fayllar ({{ $studentFiles->count() }})</p>
                                    <div class="space-y-2">
                                        @foreach($studentFiles as $sFile)
                                        <div class="flex items-center justify-between p-3 rounded-lg" style="background:#fff; border:1px solid #e5e7eb;">
                                            <div class="flex-1 min-w-0">
                                                <p style="font-size:13px; font-weight:600; color:#1f2937; margin:0;">{{ $sFile->name }}</p>
                                                <p style="font-size:11px; color:#9ca3af; margin:2px 0 0;">
                                                    {{ $sFile->original_name }} &middot;
                                                    {{ number_format($sFile->size / 1024, 1) }} KB &middot;
                                                    {{ $sFile->created_at->format('d.m.Y H:i') }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2 ml-3">
                                                <a href="{{ route('admin.students.files.download', [$student, $sFile]) }}"
                                                   style="padding:4px 10px; font-size:11px; font-weight:600; color:#fff; background:#3b82f6; border-radius:6px; text-decoration:none; transition:all 0.15s;"
                                                   onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                                                    Yuklab olish
                                                </a>
                                                <form action="{{ route('admin.students.files.delete', [$student, $sFile]) }}" method="POST"
                                                      onsubmit="return confirm('{{ addslashes($sFile->name) }} faylini o\'chirmoqchimisiz?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            style="padding:4px 10px; font-size:11px; font-weight:600; color:#fff; background:#ef4444; border:none; border-radius:6px; cursor:pointer; transition:all 0.15s;"
                                                            onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                                                        O'chirish
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @else
                                <p style="font-size:13px; color:#9ca3af; text-align:center; padding:8px 0;">Hozircha fayllar yuklanmagan</p>
                                @endif
                            </div>
                            @endif

                            {{-- Qabul ma'lumotlari --}}
                            @if($canUploadFiles)
                            <div class="mt-6" id="admission-section">
                                {{-- Header --}}
                                <div class="flex items-center justify-between px-5 py-3 rounded-t-xl cursor-pointer select-none bg-gradient-to-r from-slate-800 to-slate-600"
                                     onclick="toggleSection('admission-body','admission-arrow')">
                                    <div class="flex items-center gap-2.5">
                                        <svg class="w-5 h-5 text-white/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                        <span class="text-sm font-bold text-white tracking-wide">Qabul ma'lumotlari</span>
                                        @if($admissionData)
                                        <span class="text-[10px] bg-white/20 text-white/80 px-2.5 py-0.5 rounded-full font-medium">Oxirgi: {{ $admissionData->updated_at->format('d.m.Y H:i') }}</span>
                                        @endif
                                    </div>
                                    <svg id="admission-arrow" class="w-4 h-4 text-white/70 transition-transform duration-200" style="transform:rotate(90deg)" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                </div>

                                {{-- Body --}}
                                <div id="admission-body" class="border border-t-0 border-slate-200 rounded-b-xl bg-slate-100/50">

                                    {{-- Ma'lumotlar formasi --}}
                                    <form action="{{ route('admin.students.admission-data.save', $student) }}" method="POST" class="p-5 space-y-4">
                                        @csrf

                                        @php
                                            $sectionCard = 'group bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow';
                                            $sectionAccent = 'h-1 w-full';
                                            $sectionBody = 'p-4';
                                            $sectionTitle = 'flex items-center gap-2 mb-3.5';
                                            $sectionTitleText = 'text-[13px] font-bold text-slate-700';
                                            $sectionLabel = 'block text-[11px] font-semibold text-slate-500 mb-1.5';
                                            $sectionInput = 'w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500/15 focus:border-indigo-400 transition placeholder:text-slate-300 hover:border-slate-300';
                                            $sectionIconWrap = 'flex items-center justify-center w-7 h-7 rounded-lg';
                                        @endphp

                                        {{-- 1. Shaxsiy ma'lumotlar --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-indigo-500 to-indigo-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-indigo-50 text-indigo-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Shaxsiy ma'lumotlar</h5>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                                    @foreach([['familya','Familya'],['ism','Ism'],['otasining_ismi',"Otasining ismi"],['tugilgan_sana',"Tug'ilgan sana",'date'],['jshshir','JSHSHIR'],['jinsi','Jinsi'],['tel1','Tel 1'],['tel2','Tel 2'],['email','Email'],['millat','Millat']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="{{ $f[2] ?? 'text' }}" name="{{ $f[0] }}"
                                                               value="{{ old($f[0], $admissionData?->{$f[0]} ? (($f[2] ?? '') === 'date' ? \Carbon\Carbon::parse($admissionData->{$f[0]})->format('Y-m-d') : $admissionData->{$f[0]}) : '') }}"
                                                               class="{{ $sectionInput }}"
                                                               placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 2. Tug'ilgan joy --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-sky-500 to-sky-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-sky-50 text-sky-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Tug'ilgan joy</h5>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                    @foreach([['tugilgan_davlat','Davlat'],['tugilgan_viloyat','Viloyat'],['tugulgan_tuman','Tuman']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                               class="{{ $sectionInput }}" placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 3. Manzil --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-cyan-500 to-cyan-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-cyan-50 text-cyan-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Doimiy manzil</h5>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                                    @foreach([['doimiy_manzil','Doimiy manzil'],['yashash_davlat','Yashash davlat'],['yashash_viloyat','Yashash viloyat'],['yashash_tuman','Yashash tuman'],['yashash_manzil','Yashash manzil']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                               class="{{ $sectionInput }}" placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 4. Pasport --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-amber-500 to-amber-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-amber-50 text-amber-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Pasport ma'lumotlari</h5>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                    @foreach([['passport_seriya','Seriya'],['passport_raqam','Raqam'],['passport_sana','Berilgan sana','date'],['passport_joy','Berilgan joy']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="{{ $f[2] ?? 'text' }}" name="{{ $f[0] }}"
                                                               value="{{ old($f[0], $admissionData?->{$f[0]} ? (($f[2] ?? '') === 'date' ? \Carbon\Carbon::parse($admissionData->{$f[0]})->format('Y-m-d') : $admissionData->{$f[0]}) : '') }}"
                                                               class="{{ $sectionInput }}" placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 5. Ta'lim ma'lumotlari --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-emerald-500 to-emerald-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-emerald-50 text-emerald-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Ta'lim ma'lumotlari</h5>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                                    @foreach([['oliy_malumot',"Oliy ma'lumot"],['otm_nomi','OTM nomi'],['talim_turi',"Ta'lim turi"],['talim_shakli',"Ta'lim shakli"],['mutaxassislik','Mutaxassislik'],['toplagan_ball','Toplagan ball'],['tolov_shakli',"To'lov shakli"],['muassasa_nomi','Muassasa nomi'],['hujjat_seriya','Hujjat seriya'],['ortalacha_ball','Ortalacha ball']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                               class="{{ $sectionInput }}" placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 6. Til sertifikatlari --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-violet-500 to-violet-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-violet-50 text-violet-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Til sertifikatlari</h5>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                    @foreach([['sertifikat_turi','Sertifikat turi'],['sertifikat_ball','Sertifikat ball'],['milliy_sertifikat','Milliy sertifikat']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                               class="{{ $sectionInput }}" placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 7. Ota ma'lumotlari --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-blue-500 to-blue-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-blue-50 text-blue-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Ota ma'lumotlari</h5>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                                    @foreach([['ota_familiya','Familya'],['ota_ismi','Ismi'],['ota_sharifi','Sharifi'],['ota_tel','Tel'],['ota_ish_joyi','Ish joyi'],['ota_lavozimi','Lavozimi']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                               class="{{ $sectionInput }}" placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- 8. Ona ma'lumotlari --}}
                                        <div class="{{ $sectionCard }}">
                                            <div class="{{ $sectionAccent }} bg-gradient-to-r from-pink-500 to-pink-400"></div>
                                            <div class="{{ $sectionBody }}">
                                                <div class="{{ $sectionTitle }}">
                                                    <span class="{{ $sectionIconWrap }} bg-pink-50 text-pink-600">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                    </span>
                                                    <h5 class="{{ $sectionTitleText }}">Ona ma'lumotlari</h5>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                                    @foreach([['ona_familiya','Familya'],['ona_ismi','Ismi'],['ona_sharifi','Sharifi'],['ona_tel','Tel'],['ona_ish_joyi','Ish joyi'],['ona_lavozimi','Lavozimi']] as $f)
                                                    <div>
                                                        <label class="{{ $sectionLabel }}">{{ $f[1] }}</label>
                                                        <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                               class="{{ $sectionInput }}" placeholder="{{ $f[1] }}">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Saqlash paneli (sticky) --}}
                                        <div class="sticky bottom-0 z-10 bg-white rounded-xl shadow-md border border-slate-200 px-4 py-3 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-500">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </span>
                                                @if($admissionData)
                                                    <div class="text-xs">
                                                        <div class="font-semibold text-slate-700">Oxirgi yangilangan</div>
                                                        <div class="text-slate-400">{{ $admissionData->updated_at->format('d.m.Y H:i') }}</div>
                                                    </div>
                                                @else
                                                    <span class="text-xs text-slate-400">Hali saqlanmagan</span>
                                                @endif
                                            </div>
                                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 text-white text-sm font-bold rounded-lg hover:opacity-90 transition shadow-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                Saqlash
                                            </button>
                                        </div>
                                    </form>

                                    {{-- Hujjatlar bo'limi --}}
                                    <div class="px-5 pb-5">
                                        <div class="border-t border-slate-200 pt-5">
                                            <div class="flex items-center gap-2 mb-4">
                                                <span class="w-1 h-4 rounded-full bg-teal-500"></span>
                                                <h5 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Hujjatlar (PDF / Rasm)</h5>
                                                <span class="text-[10px] text-slate-400 font-medium ml-1">max 20MB</span>
                                            </div>

                                            @php
                                                $docTypes = [
                                                    ['short' => 'Pasport', 'full' => 'Pasport (PDF)'],
                                                    ['short' => 'Propiska', 'full' => 'Propiska (PDF)'],
                                                    ['short' => 'Attestat', 'full' => 'Attestat (PDF)'],
                                                    ['short' => 'Ruxsatnoma', 'full' => 'Ruxsatnoma (PDF)'],
                                                    ['short' => 'DTM varaqa', 'full' => 'DTM varaqa (PDF)'],
                                                    ['short' => 'Ota pasporti', 'full' => 'Ota pasporti (PDF)'],
                                                    ['short' => 'Ona pasporti', 'full' => 'Ona pasporti (PDF)'],
                                                    ['short' => 'Obyektivka', 'full' => 'Obyektivka'],
                                                    ['short' => 'Boshqa', 'full' => 'Boshqa'],
                                                ];
                                                $uploadedByName = $studentFiles->filter(fn($f) => in_array($f->name, array_column($docTypes, 'full')))->keyBy('name');
                                            @endphp

                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                                @foreach($docTypes as $doc)
                                                @php $uploaded = $uploadedByName->get($doc['full']); @endphp
                                                <div class="rounded-lg border p-3 transition-all {{ $uploaded ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30 hover:border-slate-300' }}">
                                                    {{-- Hujjat nomi va holati --}}
                                                    <div class="flex items-center gap-2 mb-2.5">
                                                        @if($uploaded)
                                                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                        </span>
                                                        @else
                                                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center">
                                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                        </span>
                                                        @endif
                                                        <div class="min-w-0">
                                                            <span class="text-xs font-bold block {{ $uploaded ? 'text-emerald-700' : 'text-slate-600' }}">{{ $doc['short'] }}</span>
                                                            @if($uploaded)
                                                            <span class="text-[10px] text-slate-400">{{ number_format($uploaded->size / 1024, 1) }} KB &middot; {{ $uploaded->created_at->format('d.m.Y') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if($uploaded)
                                                    {{-- Yuklangan fayl uchun amallar --}}
                                                    <div class="flex items-center gap-1.5">
                                                        <a href="{{ route('admin.students.files.download', [$student, $uploaded]) }}"
                                                           class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-blue-600 bg-blue-50 rounded-md hover:bg-blue-100 transition">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                                            Yuklab olish
                                                        </a>
                                                        <form action="{{ route('admin.students.admission-files.delete', [$student, $uploaded]) }}" method="POST"
                                                              onsubmit="return confirm('{{ addslashes($doc['full']) }} faylini o\'chirmoqchimisiz?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-red-500 bg-red-50 rounded-md hover:bg-red-100 transition">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                                O'chirish
                                                            </button>
                                                        </form>
                                                    </div>
                                                    @else
                                                    {{-- Fayl yuklash input --}}
                                                    <form action="{{ route('admin.students.admission-files.upload', $student) }}" method="POST" enctype="multipart/form-data">
                                                        @csrf
                                                        <input type="hidden" name="admission_file_name" value="{{ $doc['full'] }}">
                                                        <div class="flex items-center gap-2">
                                                            <input type="file" name="admission_file" accept=".pdf,.jpg,.jpeg,.png" required
                                                                   class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-slate-100 file:text-slate-600 hover:file:bg-slate-200 file:cursor-pointer file:transition">
                                                            <button type="submit" class="flex-shrink-0 p-1.5 bg-teal-600 text-white rounded-md hover:bg-teal-700 transition" title="Yuklash">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                                            </button>
                                                        </div>
                                                    </form>
                                                    @endif
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            @endif

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

    <script>
    function toggleSection(bodyId, arrowId) {
        var body = document.getElementById(bodyId);
        var arrow = document.getElementById(arrowId);
        if (body.style.display === 'none') {
            body.style.display = 'block';
            arrow.style.transform = 'rotate(90deg)';
        } else {
            body.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    </script>
</x-app-layout>
