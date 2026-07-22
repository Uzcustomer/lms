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

                              {{-- Akademik hujjatlar: farmoyish + qabul buyrug'i --}}
                              @if($canUploadFiles)
                              <div class="mt-6 p-4 rounded-lg" style="background: linear-gradient(135deg, #eff6ff, #f8fafc); border: 1px solid #bfdbfe;">
                                <h4 class="font-semibold text-base mb-1 border-b pb-2" style="color: #1e40af;">Akademik hujjatlar</h4>
                                <p style="font-size:12px; color:#64748b; margin:6px 0 14px;">Darslarga qatnashish to'g'risidagi farmoyish va o'qishga qabul qilinganlik to'g'risidagi buyruq.</p>

                                @if(session('success') && session('active_tab') === 'akademik')
                                    <div style="background:#dcfce7; border:1px solid #86efac; color:#166534; padding:8px 12px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('success') }}</div>
                                @endif
                                @if(session('error') && session('active_tab') === 'akademik')
                                    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:8px 12px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('error') }}</div>
                                @endif
                                @if($errors->any() && session('active_tab') === 'akademik')
                                    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:8px 12px; border-radius:8px; font-size:13px; margin-bottom:12px;">
                                        @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                                    </div>
                                @endif

                                <form action="{{ route('admin.students.academic-orders.save', $student) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                        {{-- Farmoyish (darslarga qatnashish) --}}
                                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                                            <h5 style="font-size:13px; font-weight:700; color:#1a3268; margin:0 0 12px;">Darslarga qatnashish farmoyishi</h5>
                                            <div style="margin-bottom:10px;">
                                                <label class="ao-label">Farmoyish raqami</label>
                                                <input type="text" name="farmoyish_number" value="{{ old('farmoyish_number', $academicOrder->farmoyish_number ?? '') }}" placeholder="Masalan: 123-F" class="ao-input">
                                            </div>
                                            <div style="margin-bottom:10px;">
                                                <label class="ao-label">Farmoyish sanasi</label>
                                                <input type="date" name="farmoyish_date" value="{{ old('farmoyish_date', isset($academicOrder->farmoyish_date) && $academicOrder->farmoyish_date ? $academicOrder->farmoyish_date->format('Y-m-d') : '') }}" class="ao-input">
                                            </div>
                                            <div>
                                                <label class="ao-label">Farmoyish fayli (PDF)</label>
                                                @if(!empty($academicOrder?->farmoyish_file_path))
                                                    <div class="ao-existing">
                                                        <a href="{{ route('admin.students.academic-orders.view', [$student, 'farmoyish']) }}" target="_blank" class="ao-view-link">
                                                            📄 {{ $academicOrder->farmoyish_file_original_name ?: 'Farmoyish.pdf' }}
                                                        </a>
                                                        <button type="submit" form="ao-del-farmoyish" onclick="return confirm('Farmoyish faylini o\'chirmoqchimisiz?')" class="ao-del-btn">O'chirish</button>
                                                    </div>
                                                @endif
                                                <input type="file" name="farmoyish_file" accept="application/pdf" class="ao-file">
                                                <p class="ao-hint">Faqat PDF. Maksimum 20 MB. Yangi fayl yuklansa eskisi almashtiriladi.</p>
                                            </div>
                                        </div>

                                        {{-- Qabul buyrug'i (o'qishga qabul) --}}
                                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                                            <h5 style="font-size:13px; font-weight:700; color:#1a3268; margin:0 0 12px;">O'qishga qabul buyrug'i</h5>
                                            <div style="margin-bottom:10px;">
                                                <label class="ao-label">Qabul buyrug'i raqami</label>
                                                <input type="text" name="qabul_number" value="{{ old('qabul_number', $academicOrder->qabul_number ?? '') }}" placeholder="Masalan: 45-Q" class="ao-input">
                                            </div>
                                            <div style="margin-bottom:10px;">
                                                <label class="ao-label">Qabul buyrug'i sanasi</label>
                                                <input type="date" name="qabul_date" value="{{ old('qabul_date', isset($academicOrder->qabul_date) && $academicOrder->qabul_date ? $academicOrder->qabul_date->format('Y-m-d') : '') }}" class="ao-input">
                                            </div>
                                            <div>
                                                <label class="ao-label">Qabul buyrug'i fayli (PDF)</label>
                                                @if(!empty($academicOrder?->qabul_file_path))
                                                    <div class="ao-existing">
                                                        <a href="{{ route('admin.students.academic-orders.view', [$student, 'qabul']) }}" target="_blank" class="ao-view-link">
                                                            📄 {{ $academicOrder->qabul_file_original_name ?: 'Qabul_buyrugi.pdf' }}
                                                        </a>
                                                        <button type="submit" form="ao-del-qabul" onclick="return confirm('Qabul buyrug\'i faylini o\'chirmoqchimisiz?')" class="ao-del-btn">O'chirish</button>
                                                    </div>
                                                @endif
                                                <input type="file" name="qabul_file" accept="application/pdf" class="ao-file">
                                                <p class="ao-hint">Faqat PDF. Maksimum 20 MB. Yangi fayl yuklansa eskisi almashtiriladi.</p>
                                            </div>
                                        </div>

                                    </div>

                                    <div style="margin-top:16px; display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                                        @if($academicOrder && $academicOrder->updated_at)
                                            <span style="font-size:11px; color:#94a3b8;">Oxirgi yangilanish: {{ $academicOrder->updated_at->format('d.m.Y H:i') }}</span>
                                        @endif
                                        <button type="submit" style="padding:9px 24px; border:none; border-radius:8px; background:linear-gradient(135deg,#1a3268,#2b5ea7); color:#fff; font-size:13px; font-weight:700; cursor:pointer;">Saqlash</button>
                                    </div>
                                </form>

                                {{-- Fayllarni o'chirish uchun alohida formalar (nested form bo'lmasligi uchun tashqarida) --}}
                                <form id="ao-del-farmoyish" action="{{ route('admin.students.academic-orders.delete-file', [$student, 'farmoyish']) }}" method="POST" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <form id="ao-del-qabul" action="{{ route('admin.students.academic-orders.delete-file', [$student, 'qabul']) }}" method="POST" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                              </div>
                              @endif

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

                            {{-- TAB 5: QABUL (Umumiy ma'lumotlar) --}}
                            @if($canUploadFiles)
                            <div id="ptab-content-qabul" class="sp-content" style="display:none;">
                                @php
                                    $isFirstCourse = (string) ($student->level_code ?? '') === '11'
                                        || str_contains(mb_strtolower((string) ($student->level_name ?? ''), 'UTF-8'), '1-kurs');
                                @endphp

                                @if($isFirstCourse && $admissionData)
                                    @include('admin.students._qabul_summary', ['student' => $student, 'admissionData' => $admissionData])
                                @else
                                    @include('admin.students._qabul_wizard', ['student' => $student, 'admissionData' => $admissionData])
                                @endif

                                @include('admin.students._admission_files_panel', ['student' => $student, 'studentFiles' => $studentFiles, 'canUploadFiles' => $canUploadFiles])
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

    /* Akademik hujjatlar (farmoyish + qabul) */
    .ao-label { font-size:12px; font-weight:600; color:#334155; display:block; margin-bottom:4px; }
    .ao-input { width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; box-sizing:border-box; }
    .ao-input:focus { outline:none; border-color:#2b5ea7; box-shadow:0 0 0 3px rgba(43,94,167,.12); }
    .ao-file { width:100%; padding:6px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; background:#fff; box-sizing:border-box; }
    .ao-hint { font-size:11px; color:#9ca3af; margin-top:4px; }
    .ao-existing { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px; padding:6px 10px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; }
    .ao-view-link { font-size:12px; font-weight:600; color:#0369a1; text-decoration:none; }
    .ao-view-link:hover { text-decoration:underline; }
    .ao-del-btn { padding:3px 10px; font-size:11px; font-weight:600; color:#fff; background:#ef4444; border:none; border-radius:6px; cursor:pointer; }
    .ao-del-btn:hover { background:#dc2626; }

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

    @php
        $initialTab = session('active_tab') === 'fayllar' ? 'qabul' : session('active_tab');
    @endphp
    @if($initialTab)
    switchProfileTab('{{ $initialTab }}');
    @if(session('active_tab') === 'qabul')
    setTimeout(function(){ if (typeof goStep === 'function') goStep(5, true); }, 100);
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
