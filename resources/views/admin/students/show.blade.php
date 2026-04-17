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
                                <form action="{{ route('admin.students.admission-data.save', $student) }}" method="POST" class="qabul-form">
                                    @csrf

                                    {{-- 1. Shaxsiy ma'lumotlar --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#6366f1;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Shaxsiy ma'lumotlar</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                                @foreach([['familya','Familya'],['ism','Ism'],['otasining_ismi',"Otasining ismi"],['email','Email'],['millat','Millat']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}"
                                                           value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
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
                                                    <input type="text" name="tugilgan_sana"
                                                           value="{{ old('tugilgan_sana', $admissionData?->tugilgan_sana ? \Carbon\Carbon::parse($admissionData->tugilgan_sana)->format('Y-m-d') : '') }}"
                                                           class="qabul-input qabul-datepicker" placeholder="Sanani tanlang">
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

                                    {{-- 1b. Pasport --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#f59e0b;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Pasport ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="qabul-label">Seriya</label>
                                                    <input type="text" name="passport_seriya"
                                                           value="{{ old('passport_seriya', $admissionData?->passport_seriya ?? '') }}"
                                                           class="qabul-input" placeholder="AA" maxlength="2" style="text-transform:uppercase;">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Raqam</label>
                                                    <input type="text" name="passport_raqam"
                                                           value="{{ old('passport_raqam', $admissionData?->passport_raqam ?? '') }}"
                                                           class="qabul-input" placeholder="1234567" maxlength="7" inputmode="numeric"
                                                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Berilgan sana</label>
                                                    <input type="text" name="passport_sana"
                                                           value="{{ old('passport_sana', $admissionData?->passport_sana ? \Carbon\Carbon::parse($admissionData->passport_sana)->format('Y-m-d') : '') }}"
                                                           class="qabul-input qabul-datepicker" placeholder="Sanani tanlang">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Berilgan joy</label>
                                                    <input type="text" name="passport_joy"
                                                           value="{{ old('passport_joy', $admissionData?->passport_joy ?? '') }}"
                                                           class="qabul-input" placeholder="Berilgan joy">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    {{-- Pasport file vars for later use --}}
                                    @php
                                        $passportFile = $studentFiles->firstWhere('name', 'Pasport nusxasi (PDF)');
                                        $photoFile = $studentFiles->firstWhere('name', '3x4 rasm');
                                    @endphp
                                    {{-- 2. Tug'ilgan joy --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#0ea5e9;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Tug'ilgan joy</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                @foreach([['tugilgan_davlat','Davlat'],['tugilgan_viloyat','Viloyat'],['tugulgan_tuman','Tuman']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           class="qabul-input" placeholder="{{ $f[1] }}">
                                                </div>
                                                @endforeach
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
                                                @foreach([['yashash_davlat','Yashayotgan davlat'],['yashash_viloyat','Yashayotgan viloyat'],['yashash_tuman','Yashayotgan tuman'],['yashash_manzil','Yashayotgan manzil (ko\'cha, uy)']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           class="qabul-input" placeholder="{{ $f[1] }}">
                                                </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-3">
                                                <label class="qabul-label">Hozirgi vaqtinchalik yashash manzilingiz</label>
                                                <input type="text" name="vaqtinchalik_manzil"
                                                       value="{{ old('vaqtinchalik_manzil', $admissionData?->vaqtinchalik_manzil ?? '') }}"
                                                       class="qabul-input" placeholder="Masalan: Termiz sh., Talabalar turar joyi, 3-bino, 215-xona">
                                            </div>
                                        </div>
                                    </div>

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
                                                    <input type="text" name="talim_davlat" value="{{ old('talim_davlat', $admissionData?->talim_davlat ?? '') }}"
                                                           class="qabul-input" placeholder="O'zbekiston Respublikasi">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim olgan viloyati</label>
                                                    <input type="text" name="talim_viloyat" value="{{ old('talim_viloyat', $admissionData?->talim_viloyat ?? '') }}"
                                                           class="qabul-input" placeholder="Viloyatni kiriting">
                                                </div>
                                                <div>
                                                    <label class="qabul-label">Ta'lim olgan tumani</label>
                                                    <input type="text" name="talim_tuman" value="{{ old('talim_tuman', $admissionData?->talim_tuman ?? '') }}"
                                                           class="qabul-input" placeholder="Tumanni kiriting">
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
                                        </div>
                                    </div>
                                    {{-- 5. Til sertifikatlari --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#8b5cf6;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Til sertifikatlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                @foreach([['sertifikat_turi','Sertifikat turi'],['sertifikat_ball','Sertifikat ball'],['milliy_sertifikat','Milliy sertifikat']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           class="qabul-input" placeholder="{{ $f[1] }}">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 7. Ota ma'lumotlari --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#3b82f6;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Ota ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                                @foreach([['ota_familiya','Familya'],['ota_ismi','Ismi'],['ota_sharifi','Sharifi'],['ota_tel','Tel'],['ota_ish_joyi','Ish joyi'],['ota_lavozimi','Lavozimi']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           class="qabul-input" placeholder="{{ $f[1] }}">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    {{-- 8. Ona ma'lumotlari --}}
                                    <div class="qabul-card">
                                        <div class="qabul-card-header" style="--accent:#ec4899;">
                                            <span class="qabul-dot"></span>
                                            <h5 class="qabul-card-title">Ona ma'lumotlari</h5>
                                        </div>
                                        <div class="qabul-card-body">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                                @foreach([['ona_familiya','Familya'],['ona_ismi','Ismi'],['ona_sharifi','Sharifi'],['ona_tel','Tel'],['ona_ish_joyi','Ish joyi'],['ona_lavozimi','Lavozimi']] as $f)
                                                <div>
                                                    <label class="qabul-label">{{ $f[1] }}</label>
                                                    <input type="text" name="{{ $f[0] }}" value="{{ old($f[0], $admissionData?->{$f[0]} ?? '') }}"
                                                           class="qabul-input" placeholder="{{ $f[1] }}">
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Saqlash tugmasi --}}
                                    <div class="qabul-card qabul-save-card">
                                        <div class="flex items-center justify-between flex-wrap gap-3">
                                            <div class="flex items-center gap-3">
                                                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white text-sm font-bold rounded-lg hover:opacity-90 transition-opacity shadow-md">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                    Ma'lumotlarni saqlash
                                                </button>
                                                @if($admissionData)
                                                <span class="text-[11px] text-slate-500">Oxirgi yangilangan: <strong>{{ $admissionData->updated_at->format('d.m.Y H:i') }}</strong></span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                {{-- Pasport nusxasi va 3x4 rasm --}}
                                <div class="qabul-card">
                                    <div class="qabul-card-header" style="--accent:#f59e0b;">
                                        <span class="qabul-dot"></span>
                                        <h5 class="qabul-card-title">Pasport nusxasi va 3x4 rasm</h5>
                                    </div>
                                    <div class="qabul-card-body">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            @php $pf = $passportFile; @endphp
                                            <div class="rounded-lg border p-3 {{ $pf ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                <div class="flex items-center gap-2 mb-2">
                                                    @if($pf)<span class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center"><svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg></span>
                                                    @else<span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center"><svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg></span>@endif
                                                    <span class="text-xs font-bold {{ $pf ? 'text-emerald-700' : 'text-slate-600' }}">Pasport nusxasi (ikala tomoni bitta PDF)</span>
                                                </div>
                                                @if($pf)
                                                <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($pf->size / 1024, 1) }} KB</span>
                                                    <a href="{{ route('admin.students.files.download', [$student, $pf]) }}" class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                    <form action="{{ route('admin.students.admission-files.delete', [$student, $pf]) }}" method="POST" onsubmit="return confirm('Pasport faylini o\'chirmoqchimisiz?')">@csrf @method('DELETE')<button type="submit" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button></form>
                                                </div>
                                                @else
                                                <form action="{{ route('admin.students.admission-files.upload', $student) }}" method="POST" enctype="multipart/form-data">@csrf<input type="hidden" name="admission_file_name" value="Pasport nusxasi (PDF)">
                                                    <div class="flex items-center gap-2"><input type="file" name="admission_file" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 file:cursor-pointer"><button type="submit" class="flex-shrink-0 p-1.5 bg-amber-500 text-white rounded-md hover:bg-amber-600 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg></button></div>
                                                </form>
                                                @endif
                                            </div>
                                            @php $ph = $photoFile; @endphp
                                            <div class="rounded-lg border p-3 {{ $ph ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                                <div class="flex items-center gap-2 mb-2">
                                                    @if($ph)<span class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center"><svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg></span>
                                                    @else<span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center"><svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg></span>@endif
                                                    <span class="text-xs font-bold {{ $ph ? 'text-emerald-700' : 'text-slate-600' }}">3x4 rasm</span>
                                                </div>
                                                @if($ph)
                                                <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($ph->size / 1024, 1) }} KB</span>
                                                    <a href="{{ route('admin.students.files.download', [$student, $ph]) }}" class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                    <form action="{{ route('admin.students.admission-files.delete', [$student, $ph]) }}" method="POST" onsubmit="return confirm('3x4 rasmni o\'chirmoqchimisiz?')">@csrf @method('DELETE')<button type="submit" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button></form>
                                                </div>
                                                @else
                                                <form action="{{ route('admin.students.admission-files.upload', $student) }}" method="POST" enctype="multipart/form-data">@csrf<input type="hidden" name="admission_file_name" value="3x4 rasm">
                                                    <div class="flex items-center gap-2"><input type="file" name="admission_file" accept=".jpg,.jpeg,.png" required class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 file:cursor-pointer"><button type="submit" class="flex-shrink-0 p-1.5 bg-amber-500 text-white rounded-md hover:bg-amber-600 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg></button></div>
                                                </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Attestat/Diplom yuklash --}}
                                @php $attestatFile = $studentFiles->firstWhere('name', 'Attestat/Diplom (PDF)'); @endphp
                                <div class="qabul-card">
                                    <div class="qabul-card-header" style="--accent:#10b981;">
                                        <span class="qabul-dot"></span>
                                        <h5 class="qabul-card-title">Attestat yoki diplomni yuklash (PDF)</h5>
                                    </div>
                                    <div class="qabul-card-body">
                                        <div class="rounded-lg border p-3 {{ $attestatFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/30' }}">
                                            <div class="flex items-center gap-2 mb-2">
                                                @if($attestatFile)<span class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center"><svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg></span>
                                                @else<span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center"><svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg></span>@endif
                                                <span class="text-xs font-bold {{ $attestatFile ? 'text-emerald-700' : 'text-slate-600' }}">Attestat yoki diplomini ilovasi bilan skaner qilib yuklash</span>
                                            </div>
                                            @if($attestatFile)
                                            <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-400">{{ number_format($attestatFile->size / 1024, 1) }} KB</span>
                                                <a href="{{ route('admin.students.files.download', [$student, $attestatFile]) }}" class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition">Yuklab olish</a>
                                                <form action="{{ route('admin.students.admission-files.delete', [$student, $attestatFile]) }}" method="POST" onsubmit="return confirm('Attestat faylini o\'chirmoqchimisiz?')">@csrf @method('DELETE')<button type="submit" class="text-[11px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded hover:bg-red-100 transition">O'chirish</button></form>
                                            </div>
                                            @else
                                            <form action="{{ route('admin.students.admission-files.upload', $student) }}" method="POST" enctype="multipart/form-data">@csrf<input type="hidden" name="admission_file_name" value="Attestat/Diplom (PDF)">
                                                <div class="flex items-center gap-2"><input type="file" name="admission_file" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 file:cursor-pointer"><button type="submit" class="flex-shrink-0 p-1.5 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg></button></div>
                                            </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- 9. Hujjatlar --}}
                                <div class="qabul-card">
                                    <div class="qabul-card-header" style="--accent:#14b8a6;">
                                        <span class="qabul-dot"></span>
                                        <h5 class="qabul-card-title">Hujjatlar (PDF / Rasm)</h5>
                                        <span class="ml-auto text-[10px] text-slate-500 font-semibold bg-slate-100 px-2 py-0.5 rounded-full">Har fayl max 1MB</span>
                                    </div>
                                    <div class="qabul-card-body">

                                            @php
                                                $docTypes = [
                                                    ['short' => 'Propiska', 'full' => 'Propiska (PDF)'],
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

                                {{-- Tozalash tugmasi — faqat ma'lumot mavjud bo'lganda --}}
                                @if($admissionData || $studentFiles->count() > 0)
                                <div class="qabul-card" style="background:linear-gradient(135deg,#fef2f2,#fff1f2); border-color:#fecaca;">
                                    <div class="flex items-center justify-between flex-wrap gap-3 p-4">
                                        <div>
                                            <p class="text-sm font-bold text-red-700">Barcha qabul ma'lumotlarini tozalash</p>
                                            <p class="text-[11px] text-red-400 mt-0.5">Form ma'lumotlari, yuklangan hujjatlar — hammasi o'chiriladi</p>
                                        </div>
                                        <form action="{{ route('admin.students.admission-data.clear', $student) }}" method="POST"
                                              onsubmit="return confirm('DIQQAT! Barcha qabul ma\'lumotlari va yuklangan hujjatlar butunlay o\'chiriladi. Davom etasizmi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-red-600 text-white text-sm font-bold rounded-lg hover:bg-red-700 transition shadow-sm">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                Tozalash
                                            </button>
                                        </form>
                                    </div>
                                </div>
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
    .qabul-form { display:flex; flex-direction:column; gap:16px; }
    #ptab-content-qabul { display:flex; flex-direction:column; gap:16px; }
    .qabul-card { background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 2px rgba(15,23,42,.04); overflow:hidden; }
    .qabul-card-header { display:flex; align-items:center; gap:10px; padding:12px 16px; background:linear-gradient(90deg, color-mix(in srgb, var(--accent,#1a3268) 8%, #ffffff), #ffffff); border-bottom:1px solid #e2e8f0; border-left:4px solid var(--accent,#1a3268); }
    .qabul-dot { width:8px; height:8px; border-radius:50%; background:var(--accent,#1a3268); flex-shrink:0; box-shadow:0 0 0 3px color-mix(in srgb, var(--accent,#1a3268) 20%, transparent); }
    .qabul-card-title { font-size:12.5px; font-weight:800; color:#1e293b; letter-spacing:.04em; text-transform:uppercase; margin:0; }
    .qabul-card-body { padding:16px; }
    .qabul-label { display:block; font-size:11.5px; font-weight:700; color:#475569; margin-bottom:6px; letter-spacing:.01em; }
    .qabul-input { width:100%; padding:8px 12px; font-size:13px; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; transition:all .15s; box-shadow:0 1px 2px rgba(15,23,42,.03); }
    .qabul-input::placeholder { color:#94a3b8; font-size:12px; }
    .qabul-input:hover { border-color:#94a3b8; }
    .qabul-input:focus { outline:none; border-color:#2b5ea7; box-shadow:0 0 0 3px rgba(43,94,167,.15); }
    .qabul-save-card { background:linear-gradient(135deg,#f8fafc,#eef2f7); border-color:#cbd5e1; padding:14px 16px; }
    select.qabul-input { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:28px; cursor:pointer; }
    .qabul-datepicker { cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:32px; }
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

    // Oliy ma'lumot toggle
    function toggleOtmNomi() {
        var sel = document.getElementById('qabul_oliy_malumot');
        var wrap = document.getElementById('qabul_otm_nomi_wrap');
        if (sel && wrap) wrap.style.display = sel.value === 'Ha' ? '' : 'none';
    }

    // Flatpickr — barcha date inputlar (init after short delay to ensure Vite bundle loaded)
    function initQabulFlatpickr() {
        if (typeof window.flatpickr === 'undefined') {
            setTimeout(initQabulFlatpickr, 200);
            return;
        }
        document.querySelectorAll('.qabul-datepicker').forEach(function(el) {
            if (el._flatpickr) return;
            flatpickr(el, { dateFormat: 'Y-m-d', allowInput: false });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { setTimeout(initQabulFlatpickr, 100); });
    } else {
        setTimeout(initQabulFlatpickr, 100);
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
    </script>
</x-app-layout>
