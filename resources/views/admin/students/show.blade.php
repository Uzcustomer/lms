<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ $student->full_name }}
            </h2>
            <a href="{{ route('admin.students.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Talabalar ro'yxatiga qaytish</a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex flex-col md:flex-row" style="gap:20px;">
                        <!-- Rasm va asosiy ma'lumot -->
                        <div class="md:w-1/4 flex flex-col items-center" style="background:linear-gradient(135deg,#1a3268,#2b5ea7);border-radius:12px;padding:1.5rem;color:#fff;">
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
                            <h3 class="text-lg font-bold text-center mb-1" style="color:#fff;">{{ $student->full_name }}</h3>
                            <p class="text-sm text-center mb-1" style="color:rgba(255,255,255,0.8);">{{ $student->student_id_number }}</p>
                            <p class="text-xs text-center mb-4" style="color:rgba(255,255,255,0.6);">HEMIS ID: {{ $student->hemis_id }}</p>

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
                                @if($canUploadFiles)<button type="button" onclick="switchProfileTab('qabul')" id="ptab-qabul" class="sp-tab">Umumiy ma'lumotlar</button>@endif
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
                                @if(session('success'))
                                <div class="mb-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold">{{ session('success') }}</div>
                                @endif
                                @if(session('error'))
                                <div class="mb-3 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-semibold">{{ session('error') }}</div>
                                @endif
                                <form action="{{ route('admin.students.admission-data.save', $student) }}" method="POST" class="qabul-form" enctype="multipart/form-data" id="qabul-main-form" novalidate>
                                    @csrf

                                <div class="flex gap-5" style="min-height:400px;">
                                    {{-- Vertical stepper navigation --}}
                                    <div class="qstep-nav flex-shrink-0" style="width:200px;">
                                        <button type="button" class="qstep-btn qstep-active" onclick="goStep(0)"><span class="qstep-num">1</span><span class="qstep-label">Shaxsiy</span></button>
                                        <button type="button" class="qstep-btn" onclick="goStep(1)"><span class="qstep-num">2</span><span class="qstep-label">Manzil</span></button>
                                        <button type="button" class="qstep-btn" onclick="goStep(2)"><span class="qstep-num">3</span><span class="qstep-label">Ta'lim</span></button>
                                        <button type="button" class="qstep-btn" onclick="goStep(3)"><span class="qstep-num">4</span><span class="qstep-label">Sertifikatlar</span></button>
                                        <button type="button" class="qstep-btn" onclick="goStep(4)"><span class="qstep-num">5</span><span class="qstep-label">Ota-ona</span></button>
                                        <button type="button" class="qstep-btn" onclick="goStep(5)"><span class="qstep-num">6</span><span class="qstep-label">Yakun</span></button>
                                    </div>

                                    {{-- Step content panels --}}
                                    <div class="flex-1 min-w-0">

                                    {{-- STEP 1: Shaxsiy --}}
                                    <div class="qstep-panel" data-step="0">

                                    {{-- 1. Shaxsiy ma'lumotlar --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#6366f1;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Shaxsiy ma'lumotlar</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                                @foreach([['familya','Familya',$student->second_name],['ism','Ism',$student->first_name],['otasining_ismi',"Otasining ismi",$student->third_name],['email','Email',''],['millat','Millat','']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}"
                                                           value="{{ old($f[0], $admissionData?->{$f[0]} ?? $f[2] ?? '') }}"
                                                           class="qabul-input" placeholder="{{ $f[1] }}">
                                                </div>
                                                @endforeach
                                                <div>
                                                    <label class="qabul-label">JSHSHIR</label>
                                                    <input type="text" name="jshshir" inputmode="numeric" pattern="[0-9]*" maxlength="14"
                                                           value="{{ old('jshshir', $admissionData?->jshshir ?? '') }}"
                                                           class="qabul-input" placeholder="14 xonali raqam"
                                                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Tug'ilgan sana</label>
                                                    <input type="date" name="tugilgan_sana"
                                                           value="{{ old('tugilgan_sana', $admissionData?->tugilgan_sana ? \Carbon\Carbon::parse($admissionData->tugilgan_sana)->format('Y-m-d') : '') }}"
                                                           class="qabul-input">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Jinsi</label>
                                                    <select name="jinsi" class="qabul-input">
                                                        <option value="">Tanlang</option>
                                                        <option value="Erkak" {{ old('jinsi', $admissionData?->jinsi) === 'Erkak' ? 'selected' : '' }}>Erkak</option>
                                                        <option value="Ayol" {{ old('jinsi', $admissionData?->jinsi) === 'Ayol' ? 'selected' : '' }}>Ayol</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Telefon raqam</label>
                                                    <input type="text" name="tel1"
                                                           value="{{ old('tel1', $admissionData?->tel1 ?? '') }}"
                                                           class="qabul-input qabul-phone" placeholder="+998 __ ___ __ __">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Qo'shimcha telefon raqam</label>
                                                    <input type="text" name="tel2"
                                                           value="{{ old('tel2', $admissionData?->tel2 ?? '') }}"
                                                           class="qabul-input qabul-phone" placeholder="+998 __ ___ __ __">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-100">
                                                <div>
                                                    <label class="qabul-label">Oliy ma'lumot mavjudligi</label>
                                                    <select name="oliy_malumot" id="qabul_oliy_malumot" class="qabul-input" onchange="toggleOtmNomi()">
                                                        <option value="Yo'q" {{ old('oliy_malumot', $admissionData?->oliy_malumot) === "Yo'q" || !old('oliy_malumot', $admissionData?->oliy_malumot) ? 'selected' : '' }}>Yo'q</option>
                                                        <option value="Ha" {{ old('oliy_malumot', $admissionData?->oliy_malumot) === 'Ha' ? 'selected' : '' }}>Ha</option>
                                                    </select>
                                                </div>
                                                <div id="qabul_otm_nomi_wrap" style="{{ old('oliy_malumot', $admissionData?->oliy_malumot) === 'Ha' ? '' : 'display:none;' }}">
                                                    <label class="qabul-label">Avval tugatgan OTM nomi</label>
                                                    <input type="text" name="otm_nomi"
                                                           value="{{ old('otm_nomi', $admissionData?->otm_nomi ?? '') }}"
                                                           class="qabul-input" placeholder="OTM nomini kiriting">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 1b. Pasport ma'lumotlari + fayllar --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#f59e0b;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Pasport ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="qabul-label">Seriya</label>
                                                    <input type="text" name="passport_seriya" value="{{ old('passport_seriya', $admissionData?->passport_seriya ?? '') }}" class="qabul-input" placeholder="AA" maxlength="2">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Raqam</label>
                                                    <input type="text" name="passport_raqam" value="{{ old('passport_raqam', $admissionData?->passport_raqam ?? '') }}" class="qabul-input" placeholder="1234567" maxlength="7" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Berilgan sana</label>
                                                    <input type="date" name="passport_sana" value="{{ old('passport_sana', $admissionData?->passport_sana ? \Carbon\Carbon::parse($admissionData->passport_sana)->format('Y-m-d') : '') }}" class="qabul-input">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Berilgan joy</label>
                                                    <input type="text" name="passport_joy" value="{{ old('passport_joy', $admissionData?->passport_joy ?? '') }}" class="qabul-input" placeholder="Berilgan joy">
                                                </div>
                                            </div>
                                            @php $passportFile = $studentFiles->firstWhere('name', 'Pasport nusxasi (PDF)'); $photoFile = $studentFiles->firstWhere('name', '3x4 rasm'); @endphp
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-100">
                                                <div class="rounded-lg border p-3 {{ $passportFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}" id="qf-pasport-nusxa">
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <span class="w-5 h-5 rounded-full {{ $passportFile ? 'bg-emerald-100' : 'bg-slate-100' }} flex items-center justify-center">@if($passportFile)<svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>@else<svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>@endif</span>
                                                        <span class="text-xs font-bold {{ $passportFile ? 'text-emerald-700' : 'text-slate-600' }}">Pasport nusxasi (ikala tomoni bitta PDF)</span>
                                                    </div>
                                                    @if($passportFile)
                                                    <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($passportFile->size / 1024, 1) }} KB</span>
                                                        <a href="{{ route('admin.students.files.download', [$student, $passportFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                        <button type="button" onclick="qabulDelete({{ $passportFile->id }},'Pasport nusxasi')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                    </div>
                                                    @else
                                                    <input type="file" name="files[Pasport nusxasi (PDF)]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 file:cursor-pointer">
                                                    @endif
                                                </div>
                                                <div class="rounded-lg border p-3 {{ $photoFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}" id="qf-3x4-rasm">
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <span class="w-5 h-5 rounded-full {{ $photoFile ? 'bg-emerald-100' : 'bg-slate-100' }} flex items-center justify-center">@if($photoFile)<svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>@else<svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>@endif</span>
                                                        <span class="text-xs font-bold {{ $photoFile ? 'text-emerald-700' : 'text-slate-600' }}">3x4 rasm</span>
                                                    </div>
                                                    @if($photoFile)
                                                    <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($photoFile->size / 1024, 1) }} KB</span>
                                                        <a href="{{ route('admin.students.files.download', [$student, $photoFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                        <button type="button" onclick="qabulDelete({{ $photoFile->id }},'3x4 rasm')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                    </div>
                                                    @else
                                                    <input type="file" name="files[3x4 rasm]" accept=".jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 file:cursor-pointer">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex justify-end mt-3"><button type="button" class="qstep-next-btn" onclick="goStep(1)">Keyingisi &rarr;</button></div>
                                    </div>{{-- /STEP 1 --}}

                                    {{-- STEP 2: Manzil --}}
                                    <div class="qstep-panel" data-step="1" style="display:none;">

                                    {{-- 2. Tug'ilgan joy --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#0ea5e9;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Tug'ilgan joy</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="qabul-label">Davlat</label>
                                                    @php $tdVal = old('tugilgan_davlat', $admissionData?->tugilgan_davlat ?? "O'zbekiston Respublikasi"); @endphp
                                                    <select name="tugilgan_davlat" class="qabul-input qabul-davlat-select" data-group="tugilgan">
                                                        <option value="O'zbekiston Respublikasi" {{ $tdVal === "O'zbekiston Respublikasi" ? 'selected' : '' }}>O'zbekiston Respublikasi</option>
                                                        <option value="Boshqa" {{ $tdVal !== "O'zbekiston Respublikasi" && $tdVal ? 'selected' : '' }}>Boshqa</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Viloyat</label>
                                                    @php $tvVal = old('tugilgan_viloyat', $admissionData?->tugilgan_viloyat ?? ''); @endphp
                                                    <select name="tugilgan_viloyat" class="qabul-input qabul-viloyat-select" data-group="tugilgan" {{ $tdVal !== "O'zbekiston Respublikasi" && $tdVal ? 'disabled' : '' }} style="{{ $tdVal !== "O'zbekiston Respublikasi" && $tdVal ? 'display:none' : '' }}">
                                                        <option value="">Tanlang...</option>
                                                    </select>
                                                    <input type="text" name="tugilgan_viloyat" class="qabul-input qabul-viloyat-text" data-group="tugilgan" value="{{ $tvVal }}" placeholder="Viloyat" {{ $tdVal === "O'zbekiston Respublikasi" || !$tdVal ? 'disabled' : '' }} style="{{ $tdVal === "O'zbekiston Respublikasi" || !$tdVal ? 'display:none' : '' }}">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Tuman</label>
                                                    @php $ttVal = old('tugulgan_tuman', $admissionData?->tugulgan_tuman ?? ''); @endphp
                                                    <select name="tugulgan_tuman" class="qabul-input qabul-tuman-select" data-group="tugilgan" {{ $tdVal !== "O'zbekiston Respublikasi" && $tdVal ? 'disabled' : '' }} style="{{ $tdVal !== "O'zbekiston Respublikasi" && $tdVal ? 'display:none' : '' }}">
                                                        <option value="">Tanlang...</option>
                                                    </select>
                                                    <input type="text" name="tugulgan_tuman" class="qabul-input qabul-tuman-text" data-group="tugilgan" value="{{ $ttVal }}" placeholder="Tuman" {{ $tdVal === "O'zbekiston Respublikasi" || !$tdVal ? 'disabled' : '' }} style="{{ $tdVal === "O'zbekiston Respublikasi" || !$tdVal ? 'display:none' : '' }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 3. Doimiy yashash manzili --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#14b8a6;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Doimiy yashash manzilingiz</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="qabul-label">Yashayotgan davlat</label>
                                                    @php $ydVal = old('yashash_davlat', $admissionData?->yashash_davlat ?? "O'zbekiston Respublikasi"); @endphp
                                                    <select name="yashash_davlat" class="qabul-input qabul-davlat-select" data-group="yashash">
                                                        <option value="O'zbekiston Respublikasi" {{ $ydVal === "O'zbekiston Respublikasi" ? 'selected' : '' }}>O'zbekiston Respublikasi</option>
                                                        <option value="Boshqa" {{ $ydVal !== "O'zbekiston Respublikasi" && $ydVal ? 'selected' : '' }}>Boshqa</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Yashayotgan viloyat</label>
                                                    @php $yvVal = old('yashash_viloyat', $admissionData?->yashash_viloyat ?? ''); @endphp
                                                    <select name="yashash_viloyat" class="qabul-input qabul-viloyat-select" data-group="yashash" {{ $ydVal !== "O'zbekiston Respublikasi" && $ydVal ? 'disabled' : '' }} style="{{ $ydVal !== "O'zbekiston Respublikasi" && $ydVal ? 'display:none' : '' }}">
                                                        <option value="">Tanlang...</option>
                                                    </select>
                                                    <input type="text" name="yashash_viloyat" class="qabul-input qabul-viloyat-text" data-group="yashash" value="{{ $yvVal }}" placeholder="Viloyat" {{ $ydVal === "O'zbekiston Respublikasi" || !$ydVal ? 'disabled' : '' }} style="{{ $ydVal === "O'zbekiston Respublikasi" || !$ydVal ? 'display:none' : '' }}">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Yashayotgan tuman</label>
                                                    @php $ytVal = old('yashash_tuman', $admissionData?->yashash_tuman ?? ''); @endphp
                                                    <select name="yashash_tuman" class="qabul-input qabul-tuman-select" data-group="yashash" {{ $ydVal !== "O'zbekiston Respublikasi" && $ydVal ? 'disabled' : '' }} style="{{ $ydVal !== "O'zbekiston Respublikasi" && $ydVal ? 'display:none' : '' }}">
                                                        <option value="">Tanlang...</option>
                                                    </select>
                                                    <input type="text" name="yashash_tuman" class="qabul-input qabul-tuman-text" data-group="yashash" value="{{ $ytVal }}" placeholder="Tuman" {{ $ydVal === "O'zbekiston Respublikasi" || !$ydVal ? 'disabled' : '' }} style="{{ $ydVal === "O'zbekiston Respublikasi" || !$ydVal ? 'display:none' : '' }}">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Yashayotgan manzil (ko'cha, uy)</label>
                                                    <input type="text" name="yashash_manzil" value="{{ old('yashash_manzil', $admissionData?->yashash_manzil ?? '') }}" class="qabul-input" placeholder="Ko'cha, uy raqami">
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <label class="qabul-label">Hozirgi vaqtinchalik yashash manzilingiz</label>
                                                <input type="text" name="vaqtinchalik_manzil"
                                                       value="{{ old('vaqtinchalik_manzil', $admissionData?->vaqtinchalik_manzil ?? '') }}"
                                                       class="qabul-input" placeholder="Masalan: Termiz sh., Talabalar turar joyi, 3-bino, 215-xona">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between mt-3"><button type="button" class="qstep-prev-btn" onclick="goStep(0)">&larr; Oldingi</button><button type="button" class="qstep-next-btn" onclick="goStep(2)">Keyingisi &rarr;</button></div>
                                    </div>{{-- /STEP 2 --}}

                                    {{-- STEP 3: Ta'lim --}}
                                    <div class="qstep-panel" data-step="2" style="display:none;">

                                    {{-- 4. Avvalgi ta'lim ma'lumotlari --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#10b981;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Avvalgi ta'lim ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="qabul-label">Davlat</label>
                                                    @php $tldVal = old('talim_davlat', $admissionData?->talim_davlat ?? "O'zbekiston Respublikasi"); @endphp
                                                    <select name="talim_davlat" class="qabul-input qabul-davlat-select" data-group="talim">
                                                        <option value="O'zbekiston Respublikasi" {{ $tldVal === "O'zbekiston Respublikasi" ? 'selected' : '' }}>O'zbekiston Respublikasi</option>
                                                        <option value="Boshqa" {{ $tldVal !== "O'zbekiston Respublikasi" && $tldVal ? 'selected' : '' }}>Boshqa</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim olgan viloyati</label>
                                                    @php $tlvVal = old('talim_viloyat', $admissionData?->talim_viloyat ?? ''); @endphp
                                                    <select name="talim_viloyat" class="qabul-input qabul-viloyat-select" data-group="talim" {{ $tldVal !== "O'zbekiston Respublikasi" && $tldVal ? 'disabled' : '' }} style="{{ $tldVal !== "O'zbekiston Respublikasi" && $tldVal ? 'display:none' : '' }}">
                                                        <option value="">Tanlang...</option>
                                                    </select>
                                                    <input type="text" name="talim_viloyat" class="qabul-input qabul-viloyat-text" data-group="talim" value="{{ $tlvVal }}" placeholder="Viloyat" {{ $tldVal === "O'zbekiston Respublikasi" || !$tldVal ? 'disabled' : '' }} style="{{ $tldVal === "O'zbekiston Respublikasi" || !$tldVal ? 'display:none' : '' }}">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim olgan tumani</label>
                                                    @php $tltVal = old('talim_tuman', $admissionData?->talim_tuman ?? ''); @endphp
                                                    <select name="talim_tuman" class="qabul-input qabul-tuman-select" data-group="talim" {{ $tldVal !== "O'zbekiston Respublikasi" && $tldVal ? 'disabled' : '' }} style="{{ $tldVal !== "O'zbekiston Respublikasi" && $tldVal ? 'display:none' : '' }}">
                                                        <option value="">Tanlang...</option>
                                                    </select>
                                                    <input type="text" name="talim_tuman" class="qabul-input qabul-tuman-text" data-group="talim" value="{{ $tltVal }}" placeholder="Tuman" {{ $tldVal === "O'zbekiston Respublikasi" || !$tldVal ? 'disabled' : '' }} style="{{ $tldVal === "O'zbekiston Respublikasi" || !$tldVal ? 'display:none' : '' }}">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim muassasasi turi</label>
                                                    <select name="talim_turi" class="qabul-input">
                                                        <option value="">Tanlang...</option>
                                                        @foreach(["Umumiy o'rta","O'rta maxsus","Akademik litsey","Kasb-hunar kolleji","Oliy ta'lim"] as $tt)
                                                        <option value="{{ $tt }}" {{ old('talim_turi', $admissionData?->talim_turi) === $tt ? 'selected' : '' }}>{{ $tt }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim muassasasi nomi</label>
                                                    <input type="text" name="muassasa_nomi" value="{{ old('muassasa_nomi', $admissionData?->muassasa_nomi ?? '') }}"
                                                           class="qabul-input" placeholder="Muassasa nomini kiriting">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">O'qigan yillari (boshi)</label>
                                                    <input type="text" name="oqigan_yili_boshi" value="{{ old('oqigan_yili_boshi', $admissionData?->oqigan_yili_boshi ?? '') }}"
                                                           class="qabul-input" placeholder="2018" maxlength="4" inputmode="numeric"
                                                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">O'qigan yillari (tugashi)</label>
                                                    <input type="text" name="oqigan_yili_tugashi" value="{{ old('oqigan_yili_tugashi', $admissionData?->oqigan_yili_tugashi ?? '') }}"
                                                           class="qabul-input" placeholder="2020" maxlength="4" inputmode="numeric"
                                                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Hujjat seriyasi va raqami</label>
                                                    <input type="text" name="hujjat_seriya" value="{{ old('hujjat_seriya', $admissionData?->hujjat_seriya ?? '') }}"
                                                           class="qabul-input" placeholder="KT777111">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">O'rtacha attestat/diplom bali</label>
                                                    <input type="text" name="ortalacha_ball" value="{{ old('ortalacha_ball', $admissionData?->ortalacha_ball ?? '') }}"
                                                           class="qabul-input" placeholder="4.5">
                                                </div>
                                            </div>
                                            @php $attestatFile = $studentFiles->firstWhere('name', 'Attestat/Diplom (PDF)'); @endphp
                                            <div class="mt-3 pt-3 border-t border-slate-100">
                                                <label class="qabul-label">Attestat yoki diplomini ilovasi bilan skaner qilib yuklash (PDF)</label>
                                                <div class="rounded-lg border p-3 {{ $attestatFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                    @if($attestatFile)
                                                    <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($attestatFile->size / 1024, 1) }} KB</span>
                                                        <a href="{{ route('admin.students.files.download', [$student, $attestatFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                        <button type="button" onclick="qabulDelete({{ $attestatFile->id }},'Attestat')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                    </div>
                                                    @else
                                                    <input type="file" name="files[Attestat/Diplom (PDF)]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 file:cursor-pointer">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    {{-- 4b. Hozirgi Oliy ta'lim muassasasi ma'lumotlari --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#0369a1;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Hozirgi Oliy ta'lim muassasasi ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="qabul-label">OTM nomi</label>
                                                    <input type="text" value="Toshkent davlat tibbiyot universiteti Termiz filiali" class="qabul-input" style="background:#f0fdf4; border-color:#86efac; color:#166534;" readonly>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim turi</label>
                                                    <select name="hozirgi_talim_turi" class="qabul-input">
                                                        <option value="">Tanlang...</option>
                                                        @foreach(['Bakalavr','Magistr'] as $ht)
                                                        <option value="{{ $ht }}" {{ old('hozirgi_talim_turi', $admissionData?->hozirgi_talim_turi) === $ht ? 'selected' : '' }}>{{ $ht }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim shakli</label>
                                                    <select name="talim_shakli" class="qabul-input">
                                                        <option value="">Tanlang...</option>
                                                        @foreach(['Kunduzgi','Kechki','Sirtqi','Onlayn'] as $ts)
                                                        <option value="{{ $ts }}" {{ old('talim_shakli', $admissionData?->talim_shakli) === $ts ? 'selected' : '' }}>{{ $ts }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Mutaxassislik</label>
                                                    <select name="mutaxassislik" class="qabul-input">
                                                        <option value="">Tanlang...</option>
                                                        @php
                                                        $mutaxassisliklar = [
                                                            'Davolash ishi','Farmatsiya','Fundamental tibbiyot','Pediatriya ishi','Stomatologiya','Tibbiy profilaktika ishi',
                                                            'Davolash ishi (Termiz tumani)','Davolash ishi (Termiz shahri)','Davolash ishi (Angor tumani)','Davolash ishi (Bandixon tumani)','Davolash ishi (Boysun tumani)','Davolash ishi (Denov tumani)','Davolash ishi (Jarqo\'rg\'on tumani)','Davolash ishi (Oltinsoy tumani)','Davolash ishi (Qiziriq tumani)','Davolash ishi (Sherobod tumani)','Davolash ishi (Qumqo\'rg\'on tumani)','Davolash ishi (Sho\'rchi tumani)','Davolash ishi (Uzun tumani)','Davolash ishi (Sariosiyo tumani)','Davolash ishi (Muzrabod tumani)','Davolash ishi (Chiroqchi tumani)','Davolash ishi (Qarshi tumani)','Davolash ishi (Qarshi shahri)','Davolash ishi (Dehqonobod tumani)','Davolash ishi (G\'uzor tumani)','Davolash ishi (Kasbi tumani)','Davolash ishi (Kitob tumani)','Davolash ishi (Ko\'kdala tumani)','Davolash ishi (Koson tumani)','Davolash ishi (Mirishkor tumani)','Davolash ishi (Muborak tumani)','Davolash ishi (Nishon tumani)','Davolash ishi (Qamashi tumani)','Davolash ishi (Shahrisabz tumani)','Davolash ishi (Yakkabog\' tumani)',
                                                            'Pediatriya ishi (Angor tumani)','Pediatriya ishi (Bandixon tumani)','Pediatriya ishi (Boysun tumani)','Pediatriya ishi (Denov tumani)','Pediatriya ishi (Denov tumani)','Pediatriya ishi (Jarqo\'rg\'on tumani)','Pediatriya ishi (Muzrabod tumani)','Pediatriya ishi (Oltinsoy tumani)','Pediatriya ishi (Qiziriq tumani)','Pediatriya ishi (Qumqo\'rg\'on tumani)','Pediatriya ishi (Sariosiyo tumani)','Pediatriya ishi (Sherobod tumani)','Pediatriya ishi (Sho\'rchi tumani)','Pediatriya ishi (Termiz shahri)','Pediatriya ishi (Termiz tumani)','Pediatriya ishi (Uzun tumani)',
                                                        ];
                                                        @endphp
                                                        @foreach($mutaxassisliklar as $mx)
                                                        <option value="{{ $mx }}" {{ old('mutaxassislik', $admissionData?->mutaxassislik) === $mx ? 'selected' : '' }}>{{ $mx }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 4c. Qabul ma'lumotlari --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#7c3aed;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Qabul ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="qabul-label">Abituriyent ID raqami</label>
                                                    <input type="text" name="abituriyent_id" value="{{ old('abituriyent_id', $admissionData?->abituriyent_id ?? '') }}"
                                                           class="qabul-input" placeholder="342234" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Javoblar varaqasi raqami</label>
                                                    <input type="text" name="javoblar_varaqasi" value="{{ old('javoblar_varaqasi', $admissionData?->javoblar_varaqasi ?? '') }}"
                                                           class="qabul-input" placeholder="1234234" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim tili</label>
                                                    <select name="talim_tili" class="qabul-input">
                                                        <option value="">Tanlang...</option>
                                                        @foreach(["O'zbekcha","Ruscha","Inglizcha"] as $tl)
                                                        <option value="{{ $tl }}" {{ old('talim_tili', $admissionData?->talim_tili) === $tl ? 'selected' : '' }}>{{ $tl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Imtihon alifbosi</label>
                                                    <select name="imtihon_alifbosi" class="qabul-input">
                                                        <option value="">Tanlang...</option>
                                                        @foreach(['Lotin','Kiril'] as $ia)
                                                        <option value="{{ $ia }}" {{ old('imtihon_alifbosi', $admissionData?->imtihon_alifbosi) === $ia ? 'selected' : '' }}>{{ $ia }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">To'plagan ball</label>
                                                    <input type="text" name="toplagan_ball" value="{{ old('toplagan_ball', $admissionData?->toplagan_ball ?? '') }}"
                                                           class="qabul-input" placeholder="Ball" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9.]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Tavsiya turi</label>
                                                    <select name="tavsiya_turi" class="qabul-input">
                                                        <option value="">Tanlang...</option>
                                                        @php
                                                        $tavsiyalar = [
                                                            "To'lov-kontrakti asosida talabalikka tavsiya etildi",
                                                            "Muddatli harbiy xizmatni o'tab harbiy qism qo'mondonligi tavsiyanomasiga ega abituriyentlar uchun ajratilgan qo'shimcha to'lov-kontrakti asosida talabalikka tavsiya etildi",
                                                            "Davlat granti asosida talabalikka tavsiya etildi",
                                                            "Davlat grantlari asosida qo'shimcha qabul (Kambag'al oila reyestriga kiritilgan oilalarning farzandlari)",
                                                            "Nogironligi bo'lgan shaxslarni uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                                            "Mutaxassisligi bo'yicha kamida besh yil mehnat stajiga ega bo'lgan xotin-qizlar tavsiyanomasiga ega abituriyentlar uchun ajratilgan qo'shimcha to'lov-kontrakti asosida talabalikka tavsiya etildi",
                                                            "Xotin-qizlarni qo'llab-quvvatlash maqsadida berilgan tavsiyanoma bilan oliy ta'lim muassasalariga ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                                            "O'zbekiston Respublikasi ichki ishlar organlari xodimlari farzandlari uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                                            "O'zbekiston Respublikasi Qurolli Kuchlari xodimlari farzandlari uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                                            "Muddatli harbiy xizmatni o'tab harbiy qism qo'mondonligi tavsiyanomasiga ega abituriyentlar uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                                            "O'zbekiston Respublikasi Bojxona xodimlari farzandlari uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                                            "\"Mehribonlik uyi\" va Bolalar shaharchasining bitiruvchilari bo'lgan chin yetim abituriyentlar uchun ajratilgan qo'shimcha davlat granti asosida talabalikka tavsiya etildi",
                                                            "Tabaqalashtirilgan to'lov kontrakt asosida",
                                                        ];
                                                        @endphp
                                                        @foreach($tavsiyalar as $tv)
                                                        <option value="{{ $tv }}" {{ old('tavsiya_turi', $admissionData?->tavsiya_turi) === $tv ? 'selected' : '' }}>{{ $tv }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            @php $ruxsatFile = $studentFiles->firstWhere('name', 'Abituriyent ruxsatnomasi (PDF)'); $dtmFile = $studentFiles->firstWhere('name', 'DTM javob varaqasi (PDF)'); @endphp
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-100">
                                                <div>
                                                    <label class="qabul-label">Abituriyent ruxsatnomasi (PDF)</label>
                                                    <div class="rounded-lg border p-3 {{ $ruxsatFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                        @if($ruxsatFile)
                                                        <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($ruxsatFile->size / 1024, 1) }} KB</span>
                                                            <a href="{{ route('admin.students.files.download', [$student, $ruxsatFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                            <button type="button" onclick="qabulDelete({{ $ruxsatFile->id }},'Ruxsatnoma')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                        </div>
                                                        @else
                                                        <input type="file" name="files[Abituriyent ruxsatnomasi (PDF)]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 file:cursor-pointer">
                                                        @endif
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="qabul-label">DTM javob varaqasi (PDF)</label>
                                                    <div class="rounded-lg border p-3 {{ $dtmFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                        @if($dtmFile)
                                                        <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($dtmFile->size / 1024, 1) }} KB</span>
                                                            <a href="{{ route('admin.students.files.download', [$student, $dtmFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                            <button type="button" onclick="qabulDelete({{ $dtmFile->id }},'DTM javob varaqasi')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                        </div>
                                                        @else
                                                        <input type="file" name="files[DTM javob varaqasi (PDF)]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 file:cursor-pointer">
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between mt-3"><button type="button" class="qstep-prev-btn" onclick="goStep(1)">&larr; Oldingi</button><button type="button" class="qstep-next-btn" onclick="goStep(3)">Keyingisi &rarr;</button></div>
                                    </div>{{-- /STEP 3 --}}

                                    {{-- STEP 4: Sertifikatlar --}}
                                    <div class="qstep-panel" data-step="3" style="display:none;">

                                    {{-- 5. Til sertifikatlari --}}
                                    @php $milliySertFile = $studentFiles->firstWhere('name', 'Milliy sertifikat'); $chetSertFile = $studentFiles->firstWhere('name', 'Chet tili sertifikati'); @endphp
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#8b5cf6;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Til sertifikatlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                {{-- Milliy sertifikat --}}
                                                <div>
                                                    <label class="qabul-label">Milliy sertifikat mavjudmi?</label>
                                                    <select name="sertifikat_turi" class="qabul-input" id="qabul_sert_select" onchange="toggleSertUpload()">
                                                        <option value="Yo'q" {{ old('sertifikat_turi', $admissionData?->sertifikat_turi) !== 'Ha' ? 'selected' : '' }}>Yo'q</option>
                                                        <option value="Ha" {{ old('sertifikat_turi', $admissionData?->sertifikat_turi) === 'Ha' || $milliySertFile ? 'selected' : '' }}>Ha</option>
                                                    </select>
                                                </div>
                                                <div id="qabul_sert_upload" style="{{ (old('sertifikat_turi', $admissionData?->sertifikat_turi) === 'Ha' || $milliySertFile) ? '' : 'display:none;' }}">
                                                    <label class="qabul-label">Milliy sertifikat faylini yuklang</label>
                                                    <div class="rounded-lg border p-3 {{ $milliySertFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                        @if($milliySertFile)
                                                        <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($milliySertFile->size / 1024, 1) }} KB</span>
                                                            <a href="{{ route('admin.students.files.download', [$student, $milliySertFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                            <button type="button" onclick="qabulDelete({{ $milliySertFile->id }},'Milliy sertifikat')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                        </div>
                                                        @else
                                                        <input type="file" name="files[Milliy sertifikat]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 file:cursor-pointer">
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            {{-- Chet tili sertifikati --}}
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3 pt-3 border-t border-slate-100">
                                                <div>
                                                    <label class="qabul-label">Chet tili sertifikati</label>
                                                    @php $chetTilVal = old('chet_til_sertifikat', $admissionData?->chet_til_sertifikat ?? 'Mavjud emas'); @endphp
                                                    <select name="chet_til_sertifikat" class="qabul-input" id="qabul_chet_sert" onchange="toggleChetSert()">
                                                        @foreach(['Mavjud emas','Milliy sertifikat','IELTS','TOEFL','DELF','DALF','Goethe-sertifikat','TOPIK','TORFL','JLPT','CEFR'] as $cs)
                                                        <option value="{{ $cs }}" {{ $chetTilVal === $cs ? 'selected' : '' }}>{{ $cs }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div id="qabul_chet_ball_wrap" style="{{ $chetTilVal !== 'Mavjud emas' ? '' : 'display:none;' }}">
                                                    <label class="qabul-label">Ball</label>
                                                    <input type="text" name="chet_til_ball" value="{{ old('chet_til_ball', $admissionData?->chet_til_ball ?? '') }}"
                                                           class="qabul-input" placeholder="Ball" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9.]/g,'')">
                                                </div>
                                                <div id="qabul_chet_file_wrap" style="{{ $chetTilVal !== 'Mavjud emas' ? '' : 'display:none;' }}">
                                                    <label class="qabul-label">Sertifikat fayli</label>
                                                    <div class="rounded-lg border p-3 {{ $chetSertFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                        @if($chetSertFile)
                                                        <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($chetSertFile->size / 1024, 1) }} KB</span>
                                                            <a href="{{ route('admin.students.files.download', [$student, $chetSertFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                            <button type="button" onclick="qabulDelete({{ $chetSertFile->id }},'Chet tili sertifikati')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                        </div>
                                                        @else
                                                        <input type="file" name="files[Chet tili sertifikati]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 file:cursor-pointer">
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between mt-3"><button type="button" class="qstep-prev-btn" onclick="goStep(2)">&larr; Oldingi</button><button type="button" class="qstep-next-btn" onclick="goStep(4)">Keyingisi &rarr;</button></div>
                                    </div>{{-- /STEP 4 --}}

                                    {{-- STEP 5: Ota-ona --}}
                                    <div class="qstep-panel" data-step="4" style="display:none;">

                                    {{-- 7. Ota ma'lumotlari --}}
                                    @php $otaPasport = $studentFiles->firstWhere('name', 'Ota pasporti (PDF)'); @endphp
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#3b82f6;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Ota ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                                @foreach([['ota_familiya','Familya'],['ota_ismi','Ismi'],['ota_sharifi','Sharifi'],['ota_tel','Telefon raqami','phone'],['ota_ish_joyi','Ish joyi'],['ota_lavozimi','Lavozimi']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           class="qabul-input {{ ($f[2] ?? '') === 'phone' ? 'qabul-phone' : '' }}" placeholder="{{ ($f[2] ?? '') === 'phone' ? '+998 __ ___ __ __' : $f[1] }}">
                                                </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-3 pt-3 border-t border-slate-100">
                                                <label class="qabul-label">Ota pasporti (PDF)</label>
                                                <div class="rounded-lg border p-3 {{ $otaPasport ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                    @if($otaPasport)
                                                    <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($otaPasport->size / 1024, 1) }} KB</span>
                                                        <a href="{{ route('admin.students.files.download', [$student, $otaPasport]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                        <button type="button" onclick="qabulDelete({{ $otaPasport->id }},'Ota pasporti')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                    </div>
                                                    @else
                                                    <input type="file" name="files[Ota pasporti (PDF)]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 file:cursor-pointer">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 8. Ona ma'lumotlari --}}
                                    @php $onaPasport = $studentFiles->firstWhere('name', 'Ona pasporti (PDF)'); @endphp
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#ec4899;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Ona ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                                @foreach([['ona_familiya','Familya'],['ona_ismi','Ismi'],['ona_sharifi','Sharifi'],['ona_tel','Telefon raqami','phone'],['ona_ish_joyi','Ish joyi'],['ona_lavozimi','Lavozimi']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           class="qabul-input {{ ($f[2] ?? '') === 'phone' ? 'qabul-phone' : '' }}" placeholder="{{ ($f[2] ?? '') === 'phone' ? '+998 __ ___ __ __' : $f[1] }}">
                                                </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-3 pt-3 border-t border-slate-100">
                                                <label class="qabul-label">Ona pasporti (PDF)</label>
                                                <div class="rounded-lg border p-3 {{ $onaPasport ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                    @if($onaPasport)
                                                    <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($onaPasport->size / 1024, 1) }} KB</span>
                                                        <a href="{{ route('admin.students.files.download', [$student, $onaPasport]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                        <button type="button" onclick="qabulDelete({{ $onaPasport->id }},'Ona pasporti')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                    </div>
                                                    @else
                                                    <input type="file" name="files[Ona pasporti (PDF)]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100 file:cursor-pointer">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between mt-3"><button type="button" class="qstep-prev-btn" onclick="goStep(3)">&larr; Oldingi</button><button type="button" class="qstep-next-btn" onclick="goStep(5)">Keyingisi &rarr;</button></div>
                                    </div>{{-- /STEP 5 --}}

                                    {{-- STEP 6: Yakun --}}
                                    <div class="qstep-panel" data-step="5" style="display:none;">

                                    {{-- Boshqa hujjatlar --}}
                                    @php $obyektivkaFile = $studentFiles->firstWhere('name', 'Obyektivka'); @endphp
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#14b8a6;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Boshqa hujjatlar</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div>
                                                <label class="qabul-label">Obyektivka</label>
                                                <div class="rounded-lg border p-3 {{ $obyektivkaFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                    @if($obyektivkaFile)
                                                    <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($obyektivkaFile->size / 1024, 1) }} KB</span>
                                                        <a href="{{ route('admin.students.files.download', [$student, $obyektivkaFile]) }}" class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                        <button type="button" onclick="qabulDelete({{ $obyektivkaFile->id }},'Obyektivka')" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button>
                                                    </div>
                                                    @else
                                                    <input type="file" name="files[Obyektivka]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 file:cursor-pointer">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between mt-3">
                                        <button type="button" class="qstep-prev-btn" onclick="goStep(4)">&larr; Oldingi</button>
                                        <div class="flex items-center gap-3">
                                            <button type="submit" style="background:#059669;" class="inline-flex items-center gap-2 px-6 py-2.5 text-white text-sm font-bold rounded-lg hover:opacity-90 transition-opacity shadow-md">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                Ma'lumotlarni saqlash
                                            </button>
                                            @if($admissionData)
                                            <span class="text-[11px] text-slate-500">Oxirgi yangilangan: <strong>{{ $admissionData->updated_at->format('d.m.Y H:i') }}</strong></span>
                                            @endif
                                        </div>
                                    </div>
                                    </div>{{-- /STEP 6 --}}

                                    </div>{{-- /flex-1 step content --}}
                                </div>{{-- /flex gap-5 container --}}
                                </form>

                                @if($admissionData || $studentFiles->count() > 0)
                                <form action="{{ route('admin.students.admission-data.clear', $student) }}" method="POST"
                                      onsubmit="return confirm('DIQQAT! Barcha qabul ma\'lumotlari va yuklangan hujjatlar butunlay o\'chiriladi. Davom etasizmi?')">
                                    @csrf @method('DELETE')
                                    <div class="qabul-card" style="border-color:#fecaca;">
                                        <div class="flex items-center justify-between flex-wrap gap-3 p-4">
                                            <div>
                                                <p class="text-sm font-bold text-red-700">Barcha ma'lumotlarni tozalash</p>
                                                <p class="text-[11px] text-red-400 mt-0.5">Form ma'lumotlari, yuklangan hujjatlar — hammasi o'chiriladi</p>
                                            </div>
                                            <button type="submit" style="background:#dc2626;" class="inline-flex items-center gap-2 px-5 py-2 text-white text-sm font-bold rounded-lg hover:opacity-90 transition shadow-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                Tozalash
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                @endif

                            </div>{{-- /qabul tab --}}
                            @endif

                        </div>{{-- /md:w-3/4 --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .sp-tabs { display:flex; gap:0; border-bottom:3px solid #1a3268; flex-wrap:wrap; }
    .sp-tab { padding:10px 18px; font-size:13px; font-weight:700; color:#64748b; background:#f1f5f9; border:1px solid #e2e8f0; border-bottom:none; border-radius:8px 8px 0 0; cursor:pointer; transition:all 0.15s; margin-bottom:-3px; flex:1; text-align:center; }
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

    /* Qabul form cards */
    .qabul-form { display:flex; flex-direction:column; gap:10px; }
    #ptab-content-qabul { display:flex; flex-direction:column; gap:10px; }
    .qstep-panel { display:flex; flex-direction:column; gap:10px; }
    .qabul-card { background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 2px 8px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.04); overflow:hidden; }
    .qabul-card-header { display:flex; align-items:center; gap:10px; padding:12px 16px; background:linear-gradient(90deg, color-mix(in srgb, var(--accent,#1a3268) 8%, #ffffff), #ffffff); border-bottom:1px solid #e2e8f0; border-left:4px solid var(--accent,#1a3268); }
    .qabul-dot { width:8px; height:8px; border-radius:50%; background:var(--accent,#1a3268); flex-shrink:0; box-shadow:0 0 0 3px color-mix(in srgb, var(--accent,#1a3268) 20%, transparent); }
    .qabul-card-title { font-size:12.5px; font-weight:800; color:#1e293b; letter-spacing:.04em; text-transform:uppercase; margin:0; }
    .qabul-card-body { padding:16px; }
    .qabul-label { display:block; font-size:13px; font-weight:700; color:#475569; margin-bottom:6px; letter-spacing:.01em; }
    .qabul-input { width:100%; padding:9px 12px; font-size:14px; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; transition:all .15s; box-shadow:0 1px 2px rgba(15,23,42,.03); text-transform:uppercase; }
    .qabul-input::placeholder { color:#94a3b8; font-size:13px; }
    .qabul-input:hover { border-color:#94a3b8; }
    .qabul-input:focus { outline:none; border-color:#2b5ea7; box-shadow:0 0 0 3px rgba(43,94,167,.15); }
    .qabul-save-card { background:linear-gradient(135deg,#f8fafc,#eef2f7); border-color:#cbd5e1; padding:14px 16px; }
    .qabul-input.qabul-error, input[type="file"].qabul-error { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,.15) !important; }
    input[type="file"].qabul-error { border:2px solid #ef4444; border-radius:8px; padding:4px; }
    .qabul-error-msg { color:#ef4444; font-size:10.5px; font-weight:600; margin-top:3px; }

    /* Vertical stepper */
    .qstep-nav { display:flex; flex-direction:column; gap:0; position:relative; }
    .qstep-nav::before { content:''; position:absolute; left:18px; top:28px; bottom:28px; width:2px; background:#e2e8f0; z-index:0; }
    .qstep-btn { display:flex; align-items:center; gap:10px; padding:12px 8px; background:none; border:none; cursor:pointer; position:relative; z-index:1; text-align:left; transition:all .15s; border-radius:8px; }
    .qstep-btn:hover { background:#f1f5f9; }
    .qstep-num { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:800; background:#e2e8f0; color:#64748b; flex-shrink:0; transition:all .2s; }
    .qstep-label { font-size:13px; font-weight:600; color:#64748b; transition:color .15s; }
    .qstep-active .qstep-num { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; box-shadow:0 2px 8px rgba(37,99,235,.35); }
    .qstep-active .qstep-label { color:#1e293b; }
    .qstep-done .qstep-num { background:#059669; color:#fff; }
    .qstep-done .qstep-label { color:#059669; }
    .qstep-next-btn,.qstep-prev-btn { padding:8px 20px; font-size:13px; font-weight:700; border:none; border-radius:8px; cursor:pointer; transition:all .15s; }
    .qstep-next-btn { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
    .qstep-next-btn:hover { opacity:.9; }
    .qstep-prev-btn { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
    .qstep-prev-btn:hover { background:#e2e8f0; }
    @media(max-width:768px) { .qstep-nav { width:100% !important; flex-direction:row; overflow-x:auto; gap:0; } .qstep-nav::before { display:none; } .qstep-label { display:none; } .qstep-btn { padding:8px 6px; } }
    select.qabul-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:28px; cursor:pointer; }
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
    // Fayl hajmi tekshiruvi
    document.addEventListener('change', function(e) {
        if (e.target.type !== 'file' || !e.target.files.length) return;
        var file = e.target.files[0];
        // Qabul hujjatlar — 1MB limit
        if (e.target.name === 'admission_file') {
            if (file.size > 1 * 1024 * 1024) {
                alert('Fayl hajmi 1 MB dan oshmasligi kerak! Tanlangan: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB');
                e.target.value = '';
                return;
            }
        }
        // Umumiy fayllar — 10MB limit
        if (e.target.name === 'file') {
            if (file.size > 10 * 1024 * 1024) {
                alert('Fayl hajmi 10 MB dan oshmasligi kerak! Tanlangan: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB');
                e.target.value = '';
                return;
            }
        }
    });

    // Vertical stepper
    var currentStep = 0;
    var totalSteps = 6;
    function goStep(n, skipValidation) {
        if (n > currentStep && !skipValidation) {
            var panel = document.querySelector('.qstep-panel[data-step="'+currentStep+'"]');
            if (panel) {
                var hasError = false;
                panel.querySelectorAll('.qabul-error').forEach(function(el) { el.classList.remove('qabul-error'); });
                panel.querySelectorAll('.qabul-error-msg').forEach(function(el) { el.remove(); });
                panel.querySelectorAll('input.qabul-input, select.qabul-input, input[type="file"][name^="files["]').forEach(function(el) {
                    if (el.disabled || el.readOnly) return;
                    if (el.closest('[style*="display:none"]') || el.closest('[style*="display: none"]')) return;
                    if (el.type === 'file') {
                        if (!el.files || !el.files.length) {
                            el.classList.add('qabul-error');
                            var msg = document.createElement('p'); msg.className = 'qabul-error-msg'; msg.textContent = 'Fayl yuklash majburiy';
                            el.parentNode.appendChild(msg); hasError = true;
                        }
                        return;
                    }
                    var val = (el.value || '').trim();
                    if (!val || (el.tagName === 'SELECT' && (val === '' || val === 'Tanlang...'))) {
                        el.classList.add('qabul-error');
                        var msg = document.createElement('p');
                        msg.className = 'qabul-error-msg';
                        msg.textContent = 'Bu maydon majburiy';
                        el.parentNode.appendChild(msg);
                        hasError = true;
                    }
                });
                if (hasError) {
                    var firstErr = panel.querySelector('.qabul-error');
                    if (firstErr) { firstErr.scrollIntoView({behavior:'smooth',block:'center'}); firstErr.focus(); }
                    return;
                }
            }
        }
        currentStep = n;
        document.querySelectorAll('.qstep-panel').forEach(function(p) { p.style.display = 'none'; });
        var target = document.querySelector('.qstep-panel[data-step="'+n+'"]');
        if (target) target.style.display = '';
        document.querySelectorAll('.qstep-btn').forEach(function(b, i) {
            b.classList.remove('qstep-active','qstep-done');
            if (i === n) b.classList.add('qstep-active');
            else if (i < n) b.classList.add('qstep-done');
        });
        var contentArea = document.getElementById('ptab-content-qabul');
        if (contentArea) contentArea.scrollTop = 0;
    }

    // AJAX fayl yuklash va o'chirish
    var qabulStudentId = {{ $student->id }};
    var qabulCsrf = '{{ csrf_token() }}';

    function qabulDelete(fileId, label) {
        if (!confirm(label + " faylini o'chirmoqchimisiz?")) return;
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = '/admin/students/' + qabulStudentId + '/admission-files/' + fileId;
        f.innerHTML = '<input type="hidden" name="_token" value="'+qabulCsrf+'"><input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(f);
        f.submit();
    }

    // Milliy sertifikat toggle
    function toggleSertUpload() {
        var sel = document.getElementById('qabul_sert_select');
        var wrap = document.getElementById('qabul_sert_upload');
        if (sel && wrap) wrap.style.display = sel.value === 'Ha' ? '' : 'none';
    }
    // Chet tili sertifikati toggle
    function toggleChetSert() {
        var sel = document.getElementById('qabul_chet_sert');
        var ballWrap = document.getElementById('qabul_chet_ball_wrap');
        var fileWrap = document.getElementById('qabul_chet_file_wrap');
        var show = sel && sel.value !== 'Mavjud emas';
        if (ballWrap) ballWrap.style.display = show ? '' : 'none';
        if (fileWrap) fileWrap.style.display = show ? '' : 'none';
    }

    // Oliy ma'lumot toggle
    function toggleOtmNomi() {
        var sel = document.getElementById('qabul_oliy_malumot');
        var wrap = document.getElementById('qabul_otm_nomi_wrap');
        if (sel && wrap) wrap.style.display = sel.value === 'Ha' ? '' : 'none';
    }

    // +998 telefon mask
    document.querySelectorAll('.qabul-phone').forEach(function(el) {
        el.addEventListener('input', function(e) {
            var raw = e.target.value.replace(/\D/g, '');
            if (raw.startsWith('998')) raw = raw.slice(3);
            if (raw.length > 9) raw = raw.slice(0, 9);
            var formatted = '+998';
            if (raw.length > 0) formatted += ' ' + raw.slice(0, 2);
            if (raw.length > 2) formatted += ' ' + raw.slice(2, 5);
            if (raw.length > 5) formatted += ' ' + raw.slice(5, 7);
            if (raw.length > 7) formatted += ' ' + raw.slice(7, 9);
            e.target.value = formatted;
        });
        el.addEventListener('focus', function(e) {
            if (!e.target.value) e.target.value = '+998 ';
        });
    });
    // Viloyat va tuman ma'lumotlari
    var uzRegions = {
        "Toshkent shahri":["Bektemir tumani","Chilonzor tumani","Mirobod tumani","Mirzo Ulug'bek tumani","Olmazor tumani","Sergeli tumani","Shayxontohur tumani","Uchtepa tumani","Yakkasaroy tumani","Yashnobod tumani","Yunusobod tumani"],
        "Toshkent viloyati":["Bekobod tumani","Bo'ka tumani","Bo'stonliq tumani","Chinoz tumani","Ohangaron tumani","Oqqo'rg'on tumani","O'rtachirchiq tumani","Parkent tumani","Piskent tumani","Qibray tumani","Toshkent tumani","Yangiyo'l tumani","Yuqorichirchiq tumani","Zangiota tumani"],
        "Andijon viloyati":["Andijon tumani","Asaka tumani","Baliqchi tumani","Bo'ston tumani","Buloqboshi tumani","Izboskan tumani","Jalaquduq tumani","Marhamat tumani","Oltinko'l tumani","Paxtaobod tumani","Qo'rg'ontepa tumani","Shahrixon tumani","Ulug'nor tumani","Xo'jaobod tumani"],
        "Buxoro viloyati":["Buxoro tumani","G'ijduvon tumani","Jondor tumani","Kogon tumani","Olot tumani","Peshku tumani","Qorako'l tumani","Qorovulbozor tumani","Romitan tumani","Shofirkon tumani","Vobkent tumani"],
        "Farg'ona viloyati":["Beshariq tumani","Bog'dod tumani","Buvayda tumani","Dang'ara tumani","Farg'ona tumani","Furqat tumani","Oltiariq tumani","O'zbekiston tumani","Qo'shtepa tumani","Quva tumani","Rishton tumani","So'x tumani","Toshloq tumani","Uchko'prik tumani","Yozyovon tumani"],
        "Jizzax viloyati":["Arnasoy tumani","Baxmal tumani","Do'stlik tumani","Forish tumani","G'allaorol tumani","Mirzacho'l tumani","Paxtakor tumani","Sharof Rashidov tumani","Yangiobod tumani","Zafarobod tumani","Zarbdor tumani","Zomin tumani"],
        "Xorazm viloyati":["Bog'ot tumani","Gurlan tumani","Hazorasp tumani","Qo'shko'pir tumani","Shovot tumani","Tuproqqal'a tumani","Urganch tumani","Xiva tumani","Xonqa tumani","Yangiariq tumani","Yangibozor tumani"],
        "Namangan viloyati":["Chortoq tumani","Chust tumani","Kosonsoy tumani","Mingbuloq tumani","Namangan tumani","Norin tumani","Pop tumani","To'raqo'rg'on tumani","Uchqo'rg'on tumani","Uychi tumani","Yangiqo'rg'on tumani"],
        "Navoiy viloyati":["Karmana tumani","Konimex tumani","Navbahor tumani","Nurota tumani","Qiziltepa tumani","Tomdi tumani","Uchquduq tumani","Xatirchi tumani"],
        "Qashqadaryo viloyati":["Chiroqchi tumani","Dehqonobod tumani","G'uzor tumani","Kamashi tumani","Kasbi tumani","Kitob tumani","Koson tumani","Mirishkor tumani","Muborak tumani","Nishon tumani","Qarshi tumani","Shahrisabz tumani","Yakkabog' tumani"],
        "Samarqand viloyati":["Bulung'ur tumani","Ishtixon tumani","Jomboy tumani","Kattaqo'rg'on tumani","Narpay tumani","Nurobod tumani","Oqdaryo tumani","Pastdarg'om tumani","Payariq tumani","Samarqand tumani","Toyloq tumani","Urgut tumani"],
        "Sirdaryo viloyati":["Boyovut tumani","Guliston tumani","Mirzaobod tumani","Oqoltin tumani","Sardoba tumani","Sayxunobod tumani","Sirdaryo tumani","Xovos tumani"],
        "Surxondaryo viloyati":["Angor tumani","Bandixon tumani","Boysun tumani","Denov tumani","Jarqo'rg'on tumani","Muzrabod tumani","Oltinsoy tumani","Qiziriq tumani","Qumqo'rg'on tumani","Sariosiyo tumani","Sherobod tumani","Sho'rchi tumani","Termiz tumani","Uzun tumani"],
        "Qoraqalpog'iston Respublikasi":["Amudaryo tumani","Beruniy tumani","Bo'zatov tumani","Chimboy tumani","Ellikqal'a tumani","Kegeyli tumani","Mo'ynoq tumani","Nukus tumani","Qanliko'l tumani","Qo'ng'irot tumani","Qorao'zak tumani","Shumanay tumani","Taxtako'pir tumani","To'rtko'l tumani","Xo'jayli tumani"]
    };
    var uzViloyatlar = Object.keys(uzRegions);

    function initLocationGroup(group) {
        var davlatSel = document.querySelector('.qabul-davlat-select[data-group="'+group+'"]');
        var vilSel = document.querySelector('.qabul-viloyat-select[data-group="'+group+'"]');
        var vilTxt = document.querySelector('.qabul-viloyat-text[data-group="'+group+'"]');
        var tumSel = document.querySelector('.qabul-tuman-select[data-group="'+group+'"]');
        var tumTxt = document.querySelector('.qabul-tuman-text[data-group="'+group+'"]');
        if (!davlatSel) return;

        var savedVil = vilTxt ? vilTxt.value || vilSel.getAttribute('data-saved') : '';
        var savedTum = tumTxt ? tumTxt.value || tumSel.getAttribute('data-saved') : '';

        function populateViloyat() {
            vilSel.innerHTML = '<option value="">Tanlang...</option>';
            uzViloyatlar.forEach(function(v) {
                var o = document.createElement('option');
                o.value = v; o.textContent = v;
                if (v === savedVil) o.selected = true;
                vilSel.appendChild(o);
            });
        }

        function populateTuman(vil) {
            tumSel.innerHTML = '<option value="">Tanlang...</option>';
            var list = uzRegions[vil] || [];
            list.forEach(function(t) {
                var o = document.createElement('option');
                o.value = t; o.textContent = t;
                if (t === savedTum) o.selected = true;
                tumSel.appendChild(o);
            });
        }

        function switchMode(isUzb) {
            if (isUzb) {
                vilSel.style.display=''; vilSel.disabled=false; vilTxt.style.display='none'; vilTxt.disabled=true;
                tumSel.style.display=''; tumSel.disabled=false; tumTxt.style.display='none'; tumTxt.disabled=true;
                populateViloyat();
                populateTuman(vilSel.value);
            } else {
                vilSel.style.display='none'; vilSel.disabled=true; vilTxt.style.display=''; vilTxt.disabled=false;
                tumSel.style.display='none'; tumSel.disabled=true; tumTxt.style.display=''; tumTxt.disabled=false;
            }
        }

        davlatSel.addEventListener('change', function() {
            savedVil = ''; savedTum = '';
            vilTxt.value = ''; tumTxt.value = '';
            switchMode(this.value === "O'zbekiston Respublikasi");
        });

        vilSel.addEventListener('change', function() {
            savedTum = '';
            populateTuman(this.value);
        });

        switchMode(davlatSel.value === "O'zbekiston Respublikasi");
    }

    // Saved values uchun data-saved atributlarni set qilish
    @if($admissionData)
    (function(){
        var pairs = {
            'tugilgan': ['{{ addslashes($admissionData->tugilgan_viloyat ?? '') }}', '{{ addslashes($admissionData->tugulgan_tuman ?? '') }}'],
            'yashash': ['{{ addslashes($admissionData->yashash_viloyat ?? '') }}', '{{ addslashes($admissionData->yashash_tuman ?? '') }}'],
            'talim': ['{{ addslashes($admissionData->talim_viloyat ?? '') }}', '{{ addslashes($admissionData->talim_tuman ?? '') }}']
        };
        Object.keys(pairs).forEach(function(g) {
            var vs = document.querySelector('.qabul-viloyat-select[data-group="'+g+'"]');
            var ts = document.querySelector('.qabul-tuman-select[data-group="'+g+'"]');
            if (vs) vs.setAttribute('data-saved', pairs[g][0]);
            if (ts) ts.setAttribute('data-saved', pairs[g][1]);
        });
    })();
    @endif

    ['tugilgan','yashash','talim'].forEach(initLocationGroup);

    @if(session('active_tab'))
    switchProfileTab('{{ session('active_tab') }}');
    @if(session('active_tab') === 'qabul')
    setTimeout(function(){ goStep(5, true); }, 100);
    @endif
    @endif

    // Form submit — validate last step
    var qabulForm = document.getElementById('qabul-main-form');
    if (qabulForm) {
        qabulForm.addEventListener('submit', function(e) {
            var panel = document.querySelector('.qstep-panel[data-step="'+currentStep+'"]');
            if (!panel) return;
            panel.querySelectorAll('.qabul-error').forEach(function(el) { el.classList.remove('qabul-error'); });
            panel.querySelectorAll('.qabul-error-msg').forEach(function(el) { el.remove(); });
            var errors = [];
            panel.querySelectorAll('input.qabul-input, select.qabul-input, input[type="file"][name^="files["]').forEach(function(el) {
                if (el.disabled || el.readOnly) return;
                if (el.closest('[style*="display:none"]') || el.closest('[style*="display: none"]')) return;
                if (el.type === 'file') {
                    if (!el.files || !el.files.length) {
                        el.classList.add('qabul-error');
                        var msg = document.createElement('p'); msg.className = 'qabul-error-msg'; msg.textContent = 'Fayl yuklash majburiy';
                        el.parentNode.appendChild(msg); errors.push(el);
                    }
                    return;
                }
                var val = (el.value || '').trim();
                if (!val || (el.tagName === 'SELECT' && (val === '' || val === 'Tanlang...'))) {
                    el.classList.add('qabul-error');
                    var msg = document.createElement('p'); msg.className = 'qabul-error-msg'; msg.textContent = 'Bu maydon majburiy';
                    el.parentNode.appendChild(msg); errors.push(el);
                }
            });
            if (errors.length > 0) { e.preventDefault(); errors[0].scrollIntoView({behavior:'smooth',block:'center'}); errors[0].focus(); }
        });
        qabulForm.addEventListener('input', function(e) {
            if (e.target.classList.contains('qabul-error')) { e.target.classList.remove('qabul-error'); var m=e.target.parentNode.querySelector('.qabul-error-msg'); if(m) m.remove(); }
        });
        qabulForm.addEventListener('change', function(e) {
            if (e.target.classList.contains('qabul-error')) {
                e.target.classList.remove('qabul-error');
                var m = e.target.parentNode.querySelector('.qabul-error-msg');
                if (m) m.remove();
            }
        });
    }
    </script>
</x-app-layout>
