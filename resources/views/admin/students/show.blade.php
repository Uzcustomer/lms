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

                            {{-- TAB NAVIGATION --}}
                            <div class="sp-tabs">
                                <button type="button" onclick="switchProfileTab('shaxsiy')" id="ptab-shaxsiy" class="sp-tab sp-tab-active">Shaxsiy</button>
                                <button type="button" onclick="switchProfileTab('akademik')" id="ptab-akademik" class="sp-tab">Akademik</button>
                                <button type="button" onclick="switchProfileTab('tashkiliy')" id="ptab-tashkiliy" class="sp-tab">Tashkiliy</button>
                                <button type="button" onclick="switchProfileTab('manzil')" id="ptab-manzil" class="sp-tab">Manzil</button>
                                <button type="button" onclick="switchProfileTab('fayllar')" id="ptab-fayllar" class="sp-tab">Fayllar</button>
                                @if($canUploadFiles)<button type="button" onclick="switchProfileTab('qabul')" id="ptab-qabul" class="sp-tab">Qabul</button>@endif
                                <button type="button" onclick="switchProfileTab('tizim')" id="ptab-tizim" class="sp-tab">Tizim</button>
                            </div>

                            {{-- TAB 1: SHAXSIY --}}
                            <div id="ptab-content-shaxsiy" class="sp-content">
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="sp-card">
                                    <h4 class="sp-title">Shaxsiy ma'lumotlar</h4>
                                    <table class="sp-table">
                                        <tr><td>F.I.O</td><td>{{ $student->full_name ?? '-' }}</td></tr>
                                        <tr><td>Qisqa nomi</td><td>{{ $student->short_name ?? '-' }}</td></tr>
                                        <tr><td>Ismi</td><td>{{ $student->first_name ?? '-' }}</td></tr>
                                        <tr><td>Familyasi</td><td>{{ $student->second_name ?? '-' }}</td></tr>
                                        <tr><td>Otasining ismi</td><td>{{ $student->third_name ?? '-' }}</td></tr>
                                        <tr><td>Talaba ID</td><td><strong>{{ $student->student_id_number ?? '-' }}</strong></td></tr>
                                        <tr><td>Tug'ilgan sana</td><td>{{ $student->birth_date ? $student->birth_date->format('d.m.Y') : '-' }}</td></tr>
                                        <tr><td>Jinsi</td><td>{{ $student->gender_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->gender_code ?? '-' }})</span></td></tr>
                                        <tr><td>Fuqaroligi</td><td>{{ $student->citizenship_name ?? '-' }}</td></tr>
                                    </table>
                                </div>
                                <div class="sp-card">
                                    <h4 class="sp-title">Akademik ko'rsatkichlar</h4>
                                    <table class="sp-table">
                                        <tr><td>O'rtacha GPA</td><td><span class="font-bold {{ ($student->avg_gpa ?? 0) >= 3.5 ? 'text-green-600' : (($student->avg_gpa ?? 0) >= 2.5 ? 'text-yellow-600' : 'text-red-600') }}">{{ $student->avg_gpa ?? '-' }}</span></td></tr>
                                        <tr><td>O'rtacha baho</td><td>{{ $student->avg_grade ?? '-' }}</td></tr>
                                        <tr><td>Jami kredit</td><td>{{ $student->total_credit ?? '-' }}</td></tr>
                                        <tr><td>Jami yuklama</td><td>{{ $student->total_acload ?? '-' }}</td></tr>
                                        <tr><td>Kirgan yili</td><td>{{ $student->year_of_enter ?? '-' }}</td></tr>
                                        <tr><td>Bitiruvchi</td><td>{{ $student->is_graduate ? 'Ha' : 'Yo\'q' }}</td></tr>
                                        <tr><td>Yotoqxona</td><td>{{ $student->roommate_count ?? '-' }}</td></tr>
                                    </table>
                                </div>
                              </div>
                            </div>

                            {{-- TAB 2: AKADEMIK --}}
                            <div id="ptab-content-akademik" class="sp-content" style="display:none;">
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <div class="sp-card">
                                    <h4 class="sp-title">Kurs / Semestr / Yil</h4>
                                    <table class="sp-table">
                                        <tr><td>Kurs</td><td>{{ $student->level_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->level_code ?? '-' }})</span></td></tr>
                                        <tr><td>Semestr</td><td>{{ $student->semester_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->semester_code ?? '-' }})</span></td></tr>
                                        <tr><td>O'quv yili</td><td>{{ $student->education_year_name ?? '-' }}</td></tr>
                                        <tr><td>Ta'lim turi</td><td>{{ $student->education_type_name ?? '-' }}</td></tr>
                                        <tr><td>Ta'lim shakli</td><td>{{ $student->education_form_name ?? '-' }}</td></tr>
                                    </table>
                                </div>
                                <div class="sp-card">
                                    <h4 class="sp-title">Holat / Kategoriya</h4>
                                    <table class="sp-table">
                                        <tr><td>To'lov shakli</td><td>{{ $student->payment_form_name ?? '-' }}</td></tr>
                                        <tr><td>Talaba turi</td><td>{{ $student->student_type_name ?? '-' }}</td></tr>
                                        <tr><td>Talaba holati</td><td>{{ $student->student_status_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->student_status_code ?? '-' }})</span></td></tr>
                                        <tr><td>Ijtimoiy toifa</td><td>{{ $student->social_category_name ?? '-' }}</td></tr>
                                        <tr><td>Turar joy</td><td>{{ $student->accommodation_name ?? '-' }}</td></tr>
                                    </table>
                                </div>

                              </div>
                            </div>

                            {{-- TAB 3: TASHKILIY --}}
                            <div id="ptab-content-tashkiliy" class="sp-content" style="display:none;">
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="sp-card">
                                    <h4 class="sp-title">Universitet / Kafedra</h4>
                                    <table class="sp-table">
                                        <tr><td>Universitet</td><td>{{ $student->university_name ?? '-' }}</td></tr>
                                        <tr><td>Fakultet</td><td>{{ $student->department_name ?? '-' }} <span class="text-gray-400 text-xs">({{ $student->department_code ?? '-' }})</span></td></tr>
                                        <tr><td>Yo'nalish</td><td>{{ $student->specialty_name ?? '-' }}</td></tr>
                                        <tr><td>Guruh</td><td><strong>{{ $student->group_name ?? '-' }}</strong> <span class="text-gray-400 text-xs">({{ $student->language_name ?? '-' }})</span></td></tr>
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

                              </div>
                            </div>

                            {{-- TAB 4: MANZIL --}}
                            <div id="ptab-content-manzil" class="sp-content" style="display:none;">
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="sp-card">
                                    <h4 class="sp-title">Manzil</h4>
                                    <table class="sp-table">
                                        <tr><td>Davlat</td><td>{{ $student->country_name ?? '-' }}</td></tr>
                                        <tr><td>Viloyat</td><td>{{ $student->province_name ?? '-' }}</td></tr>
                                        <tr><td>Tuman</td><td>{{ $student->district_name ?? '-' }}</td></tr>
                                        <tr><td>Hudud</td><td>{{ $student->terrain_name ?? '-' }}</td></tr>
                                        <tr><td>Turar joy</td><td>{{ $student->accommodation_name ?? '-' }}</td></tr>
                                    </table>
                                </div>
                              </div>
                            </div>

                            {{-- TAB 5: FAYLLAR --}}
                            <div id="ptab-content-fayllar" class="sp-content" style="display:none;">
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

                            </div>{{-- /fayllar tab --}}

                            {{-- TAB 6: QABUL --}}
                            @if($canUploadFiles)
                            <div id="ptab-content-qabul" class="sp-content" style="display:none;">
                            <div id="admission-section">
                                <div id="admission-body" style="border:1px solid #dbe4ef; border-top:none; border-radius:0 0 10px 10px; padding:20px; background:#fff;">

                                    <form action="{{ route('admin.students.admission-data.save', $student) }}" method="POST">
                                        @csrf

                                        {{-- SHAXSIY MA'LUMOTLAR --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Shaxsiy ma'lumotlar</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['familya','Familya'],['ism','Ism'],['otasining_ismi',"Otasining ismi"],
                                                    ['tugilgan_sana',"Tug'ilgan sana",'date'],['jshshir','JSHSHIR'],
                                                    ['jinsi','Jinsi'],['tel1','Tel 1'],['tel2','Tel 2'],
                                                    ['email','Email'],['millat','Millat'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="{{ $f[2] ?? 'text' }}" name="{{ $f[0] }}"
                                                           value="{{ old($f[0], $admissionData?->{$f[0]} ? ($f[2] ?? '' === 'date' ? \Carbon\Carbon::parse($admissionData->{$f[0]})->format('Y-m-d') : $admissionData->{$f[0]}) : '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- TUG'ILGAN JOY --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Tug'ilgan joy</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['tugilgan_davlat','Davlat'],['tugilgan_viloyat','Viloyat'],['tugulgan_tuman','Tuman'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- MANZIL --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Manzil</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['doimiy_manzil','Doimiy manzil'],
                                                    ['yashash_davlat','Yashash davlat'],['yashash_viloyat','Yashash viloyat'],
                                                    ['yashash_tuman','Yashash tuman'],['yashash_manzil','Yashash manzil'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- PASPORT --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Pasport</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['passport_seriya','Seriya'],['passport_raqam','Raqam'],
                                                    ['passport_sana','Berilgan sana','date'],['passport_joy','Berilgan joy'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="{{ $f[2] ?? 'text' }}" name="{{ $f[0] }}"
                                                           value="{{ old($f[0], $admissionData?->{$f[0]} ? ($f[2] ?? '' === 'date' ? \Carbon\Carbon::parse($admissionData->{$f[0]})->format('Y-m-d') : $admissionData->{$f[0]}) : '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- TA'LIM --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Ta'lim ma'lumotlari</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['oliy_malumot',"Oliy ma'lumot"],['otm_nomi','OTM nomi'],
                                                    ['talim_turi',"Ta'lim turi"],['talim_shakli',"Ta'lim shakli"],
                                                    ['mutaxassislik','Mutaxassislik'],['toplagan_ball','Toplagan ball'],
                                                    ['tolov_shakli',"To'lov shakli"],['muassasa_nomi','Muassasa nomi'],
                                                    ['hujjat_seriya','Hujjat seriya'],['ortalacha_ball','Ortalacha ball'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- TIL SERTIFIKATLARI --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Til sertifikatlari</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['sertifikat_turi','Sertifikat turi'],['sertifikat_ball','Sertifikat ball'],
                                                    ['milliy_sertifikat','Milliy sertifikat'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- OTA --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Ota ma'lumotlari</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['ota_familiya','Familya'],['ota_ismi','Ismi'],['ota_sharifi','Sharifi'],
                                                    ['ota_tel','Tel'],['ota_ish_joyi','Ish joyi'],['ota_lavozimi','Lavozimi'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- ONA --}}
                                        <div style="margin-bottom:20px;">
                                            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px;">Ona ma'lumotlari</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px;">
                                                @foreach([
                                                    ['ona_familiya','Familya'],['ona_ismi','Ismi'],['ona_sharifi','Sharifi'],
                                                    ['ona_tel','Tel'],['ona_ish_joyi','Ish joyi'],['ona_lavozimi','Lavozimi'],
                                                ] as $f)
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;box-sizing:border-box;background:#f8fafc;">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div>
                                            <button type="submit" style="padding:9px 28px; background:linear-gradient(135deg,#1a3268,#2b5ea7); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; transition:opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                                Saqlash
                                            </button>
                                            @if($admissionData)
                                            <span style="font-size:11px; color:#64748b; margin-left:12px;">Oxirgi yangilangan: {{ $admissionData->updated_at->format('d.m.Y H:i') }}</span>
                                            @endif
                                        </div>
                                    </form>

                                    {{-- PDF FAYLLAR --}}
                                    <div style="margin-top:24px; border-top:2px solid #e2e8f0; padding-top:16px;">
                                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#475569; margin-bottom:12px;">Hujjatlar (PDF/rasm)</div>

                                        {{-- Fayl yuklash --}}
                                        <form action="{{ route('admin.students.admission-files.upload', $student) }}" method="POST" enctype="multipart/form-data" style="margin-bottom:16px;">
                                            @csrf
                                            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">Hujjat nomi</label>
                                                    <select name="admission_file_name" style="padding:7px 10px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;background:#f8fafc;min-width:180px;">
                                                        @foreach(['Pasport (PDF)','Propiska (PDF)','Attestat (PDF)','Ruxsatnoma (PDF)','DTM varaqa (PDF)',"Ota pasporti (PDF)","Ona pasporti (PDF)",'Obyektivka','Boshqa'] as $opt)
                                                        <option>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">Fayl (PDF/JPG/PNG, max 20MB)</label>
                                                    <input type="file" name="admission_file" accept=".pdf,.jpg,.jpeg,.png" required
                                                           style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:7px;font-size:12px;background:#fff;">
                                                </div>
                                                <div>
                                                    <button type="submit" style="padding:8px 20px; background:linear-gradient(135deg,#166534,#16a34a); color:#fff; border:none; border-radius:7px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap;">
                                                        Yuklash
                                                    </button>
                                                </div>
                                            </div>
                                        </form>

                                        {{-- Yuklangan hujjatlar --}}
                                        @php
                                            $admissionFileNames = ['Pasport (PDF)','Propiska (PDF)','Attestat (PDF)','Ruxsatnoma (PDF)','DTM varaqa (PDF)',"Ota pasporti (PDF)","Ona pasporti (PDF)",'Obyektivka','Boshqa'];
                                            $admissionFiles = $studentFiles->filter(fn($f) => in_array($f->name, $admissionFileNames));
                                        @endphp
                                        @if($admissionFiles->count() > 0)
                                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                            @foreach($admissionFiles as $af)
                                            <div style="display:flex; align-items:center; gap:6px; padding:6px 12px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                <span style="font-size:12px; font-weight:600; color:#0f172a;">{{ $af->name }}</span>
                                                <a href="{{ route('admin.students.files.download', [$student, $af]) }}"
                                                   style="font-size:11px; color:#2563eb; text-decoration:none; font-weight:600;">↓</a>
                                                <form action="{{ route('admin.students.admission-files.delete', [$student, $af]) }}" method="POST" style="display:inline;"
                                                      onsubmit="return confirm('{{ addslashes($af->name) }} faylini o\'chirmoqchimisiz?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:13px;padding:0;line-height:1;">×</button>
                                                </form>
                                            </div>
                                            @endforeach
                                        </div>
                                        @else
                                        <p style="font-size:12px; color:#9ca3af;">Hujjatlar yuklanmagan</p>
                                        @endif
                                    </div>

                                </div>
                            </div>
                            </div>{{-- /qabul tab --}}
                            @endif

                            {{-- TAB 7: TIZIM --}}
                            <div id="ptab-content-tizim" class="sp-content" style="display:none;">
                            <div class="sp-card">
                                <h4 class="sp-title">Tizim ma'lumotlari</h4>
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
                            </div>{{-- /tizim tab --}}

                        </div>{{-- /md:w-3/4 --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .sp-tabs { display:flex; gap:0; border-bottom:3px solid #1a3268; flex-wrap:wrap; }
    .sp-tab { padding:10px 18px; font-size:13px; font-weight:700; color:#64748b; background:#f1f5f9; border:1px solid #e2e8f0; border-bottom:none; border-radius:8px 8px 0 0; cursor:pointer; transition:all 0.15s; margin-bottom:-3px; }
    .sp-tab:hover { background:#e2e8f0; color:#1e293b; }
    .sp-tab-active { background:linear-gradient(135deg,#1a3268,#2b5ea7) !important; color:#fff !important; border-color:#1a3268; }
    .sp-content { background:#fff; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 10px 10px; padding:20px; }
    .sp-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; }
    .sp-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#1a3268; border-bottom:2px solid #dbe4ef; padding-bottom:8px; margin-bottom:12px; }
    .sp-table { width:100%; font-size:13px; border-collapse:collapse; }
    .sp-table td:first-child { padding:6px 8px; color:#64748b; font-weight:600; width:40%; white-space:nowrap; }
    .sp-table td:last-child { padding:6px 8px; color:#0f172a; }
    .sp-table tr:nth-child(even) { background:#f0f4f8; }
    .sp-table tr:hover { background:#e8edf5; }
    </style>

    <script>
    function switchProfileTab(tab) {
        document.querySelectorAll('.sp-content').forEach(function(el) { el.style.display = 'none'; });
        document.querySelectorAll('.sp-tab').forEach(function(el) { el.classList.remove('sp-tab-active'); });
        var content = document.getElementById('ptab-content-' + tab);
        var btn = document.getElementById('ptab-' + tab);
        if (content) content.style.display = 'block';
        if (btn) btn.classList.add('sp-tab-active');
    }
    function toggleSection(bodyId, arrowId) {
        var body = document.getElementById(bodyId);
        var arrow = document.getElementById(arrowId);
        if (body.style.display === 'none') {
            body.style.display = 'block';
            if (arrow) arrow.style.transform = 'rotate(90deg)';
        } else {
            body.style.display = 'none';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    }
    </script>
</x-app-layout>
