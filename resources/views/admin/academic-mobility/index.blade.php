<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Akademik mobillik</h2>
    </x-slot>

    @if($errors->any())
        <div class="mb-4 rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700">
            <strong class="font-bold">Arizani yuborishda xatolik:</strong>
            <ul class="mt-2 list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <form id="search-form" method="GET" action="{{ route('admin.academic-mobility.index') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="min-width:160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                                <select id="education_type" name="education_type" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($filters['educationTypes'] as $item)
                                        <option value="{{ $item->education_type_code }}" @selected((string) request('education_type') === (string) $item->education_type_code)>{{ $item->education_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex:1;min-width:200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                                <select id="department" name="department" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($filters['departments'] as $item)
                                        <option value="{{ $item->department_id }}" @selected((string) request('department') === (string) $item->department_id)>{{ $item->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex:1;min-width:240px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                                <select id="specialty" name="specialty" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($filters['specialties'] as $item)
                                        <option value="{{ $item->specialty_id }}" @selected((string) request('specialty') === (string) $item->specialty_id)>{{ $item->specialty_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:90px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                                <select id="per_page" name="per_page" class="select2" style="width:100%;">
                                    @foreach([10, 25, 50, 100] as $size)
                                        <option value="{{ $size }}" @selected((int) request('per_page', 50) === $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-row">
                            <div class="filter-item" style="min-width:140px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select id="level_code" name="level_code" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($filters['levels'] as $item)
                                        <option value="{{ $item->level_code }}" @selected((string) request('level_code') === (string) $item->level_code)>{{ $item->level_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:150px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                                <select id="semester_code" name="semester_code" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($filters['semesters'] as $item)
                                        <option value="{{ $item->semester_code }}" @selected((string) request('semester_code') === (string) $item->semester_code)>{{ $item->semester_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:170px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <select id="group" name="group" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($filters['groups'] as $item)
                                        <option value="{{ $item->group_id }}" @selected((string) request('group') === (string) $item->group_id)>{{ $item->group_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex:1;min-width:200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.Sh</label>
                                <input type="text" name="full_name" value="{{ request('full_name') }}" placeholder="Obidov Zohid" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width:140px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Talaba ID</label>
                                <input type="text" name="student_id_number" value="{{ request('student_id_number') }}" placeholder="1234" class="filter-input">
                            </div>
                            @if($filters['countries']->count() > 1)
                                <div class="filter-item" style="min-width:140px;">
                                    <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Davlati</label>
                                    <select name="country" class="select2" style="width:100%;">
                                        <option value="">Barchasi</option>
                                        @foreach($filters['countries'] as $country)
                                            <option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="filter-item" style="min-width:170px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Fayllar</label>
                                <select name="has_files" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    <option value="yes" @selected(request('has_files') === 'yes')>Yuklangan</option>
                                    <option value="no" @selected(request('has_files') === 'no')>Yuklanmagan</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ec4899;"></span> Umumiy ma'lumotlar</label>
                                <select name="has_admission_data" class="select2" style="width:100%;">
                                    <option value="">Barchasi</option>
                                    <option value="yes" @selected(request('has_admission_data') === 'yes')>To'ldirilgan</option>
                                    <option value="no" @selected(request('has_admission_data') === 'no')>To'ldirilmagan</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:180px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#22c55e;"></span> Holati</label>
                                <select name="student_status" class="select2" style="width:100%;">
                                    <option value="" @selected($selectedStatus === '')>Barchasi</option>
                                    @foreach($filters['statuses'] as $item)
                                        <option value="{{ $item->student_status_code }}" @selected((string) $selectedStatus === (string) $item->student_status_code)>{{ $item->student_status_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width:120px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="student-summary">
                    <span class="total-badge">Jami: {{ $students->total() }} ta talaba</span>
                    <a href="{{ route('admin.academic-mobility.applications') }}" class="applications-btn">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                        </svg>
                        Arizalar
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>F.I.Sh</th>
                                <th>HEMIS ID</th>
                                <th>Talaba ID</th>
                                <th>Ta'lim turi</th>
                                <th>Fakultet</th>
                                <th>Yo'nalish</th>
                                <th>Kurs</th>
                                <th>Semestr</th>
                                <th>Guruh</th>
                                <th style="text-align:center;">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $student)
                                <tr>
                                    <td><span class="student-name-link">{{ $student->full_name }}</span></td>
                                    <td style="color:#64748b;">{{ $student->hemis_id }}</td>
                                    <td style="color:#64748b;">{{ $student->student_id_number }}</td>
                                    <td>
                                        <span class="text-cell">{{ $student->education_type_name }}</span>
                                        <span style="font-size:11px;color:#94a3b8;">{{ $student->education_form_name }}</span>
                                    </td>
                                    <td><span class="text-cell text-emerald">{{ $student->department_name }}</span></td>
                                    <td><span class="text-cell text-cyan" title="{{ $student->specialty_name }}">{{ Str::limit($student->specialty_name, 30) }}</span></td>
                                    <td><span class="badge badge-violet">{{ $student->level_name }}</span></td>
                                    <td><span class="badge badge-teal">{{ $student->semester_name }}</span></td>
                                    <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-action btn-action-green"
                                                data-student-id="{{ $student->id }}"
                                                data-student-name="{{ $student->full_name }}"
                                                data-student-number="{{ $student->student_id_number }}"
                                                data-student-phone="{{ $student->phone }}"
                                                onclick="openMobilityModal(this)">
                                            Ariza berish
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" style="padding:40px;text-align:center;color:#64748b;">Filtr bo'yicha talabalar topilmadi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($students->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $students->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <div id="mobility-modal" class="fixed inset-0 z-[100] items-center justify-center bg-slate-900/65 p-4 backdrop-blur-sm" style="display:none;" role="dialog" aria-modal="true">
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/20 bg-white shadow-2xl">
            <div class="relative overflow-hidden bg-gradient-to-r from-[#1f4f91] via-[#2b67ae] to-[#3b82c4] px-6 py-5 text-white">
                <div class="absolute -right-10 -top-16 h-40 w-40 rounded-full bg-white/10"></div>
                <div class="relative flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m-5-8.5V16c0 1.1 2.24 2 5 2s5-.9 5-2v-4.5"/>
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-bold">Akademik mobillik arizasi</h2>
                            <p class="mt-0.5 text-xs text-blue-100">Talaba ma'lumotlarini tekshirib, arizani yuboring</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeMobilityModal()" class="rounded-full p-2 transition hover:bg-white/15" aria-label="Yopish">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.academic-mobility.store') }}" enctype="multipart/form-data" class="bg-slate-50/70">
                @csrf
                <input type="hidden" name="student_id" id="mobility-student-id">

                <div class="space-y-4 p-6">
                    <div class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19a6 6 0 00-12 0m9-11a4 4 0 11-8 0 4 4 0 018 0zm4 3h5m-2.5-2.5v5"/>
                            </svg>
                        </span>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-blue-500">Tanlangan talaba</div>
                            <div id="mobility-student-meta" class="mt-0.5 font-bold text-slate-800"></div>
                        </div>
                    </div>

                    <div>
                        <label for="mobility-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Talaba telefon raqami <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3l2 5-2 1a16 16 0 007 7l1-2 5 2v3a2 2 0 01-2 2h-1C10 21 3 14 3 6V5z"/>
                            </svg>
                            <input id="mobility-phone" name="phone" required maxlength="50" placeholder="+998 90 123 45 67"
                                   class="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label for="mobility-reason" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Ariza berish sababi <span class="text-red-500">*</span>
                        </label>
                        <textarea id="mobility-reason" name="reason" required minlength="5" maxlength="3000" rows="4"
                                  placeholder="Akademik mobillik arizasining sababini batafsil kiriting..."
                                  class="w-full resize-y rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        <p class="mt-1 text-xs text-slate-400">Sababni tushunarli va to'liq yozing.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">Ariza hujjati</label>
                        <label for="mobility-document"
                               class="group flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-blue-200 bg-white px-5 py-5 text-center transition hover:border-blue-500 hover:bg-blue-50/40">
                            <span class="mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-600 transition group-hover:bg-blue-100">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0L7 9m5-5 5 5M5 15v4h14v-4"/>
                                </svg>
                            </span>
                            <span id="mobility-file-name" class="text-sm font-semibold text-slate-700">Faylni tanlash uchun bosing</span>
                            <span class="mt-1 text-xs text-slate-400">PDF, DOC, DOCX, JPG yoki PNG · maksimal 10 MB</span>
                            <input id="mobility-document" type="file" name="document"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="hidden" onchange="updateMobilityFile(this)">
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4">
                    <button type="button" onclick="closeMobilityModal()"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Bekor qilish
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-[#2b5ea7] to-[#3b82f6] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:shadow-md">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12l14-7-4 14-3-6-7-1z"/>
                        </svg>
                        Arizani yuborish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function stripSpecialChars(value) {
            return value.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase();
        }

        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        $(document).ready(function () {
            $('.select2').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                });
            });
        });

        function openMobilityModal(button) {
            document.getElementById('mobility-student-id').value = button.dataset.studentId;
            document.getElementById('mobility-phone').value = button.dataset.studentPhone || '';
            document.getElementById('mobility-student-meta').textContent = button.dataset.studentName + ' · ID: ' + (button.dataset.studentNumber || '-');
            document.getElementById('mobility-reason').value = '';
            document.getElementById('mobility-document').value = '';
            document.getElementById('mobility-file-name').textContent = 'Faylni tanlash uchun bosing';
            document.getElementById('mobility-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function updateMobilityFile(input) {
            var label = document.getElementById('mobility-file-name');
            if (!input.files || !input.files.length) {
                label.textContent = 'Faylni tanlash uchun bosing';
                return;
            }

            var file = input.files[0];
            var sizeMb = (file.size / 1024 / 1024).toFixed(2);
            label.textContent = file.name + ' · ' + sizeMb + ' MB';
        }

        function closeMobilityModal() {
            document.getElementById('mobility-modal').style.display = 'none';
            document.body.style.overflow = '';
        }

        document.getElementById('mobility-modal').addEventListener('click', function (event) {
            if (event.target === this) closeMobilityModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMobilityModal();
        });
    </script>

    <style>
        .filter-container { padding:16px 20px 12px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef; }
        .filter-row { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;align-items:flex-end; }
        .filter-row:last-child { margin-bottom:0; }
        .filter-label { display:flex;align-items:center;gap:5px;margin-bottom:4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#475569; }
        .fl-dot { width:7px;height:7px;border-radius:50%;display:inline-block;flex-shrink:0; }
        .filter-input { width:100%;height:36px;padding:0 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-size:.8rem;font-weight:500;color:#1e293b;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:all .2s;box-sizing:border-box; }
        .filter-input:hover { border-color:#2b5ea7;box-shadow:0 0 0 2px rgba(43,94,167,.1); }
        .filter-input:focus { outline:none;border-color:#2b5ea7;box-shadow:0 0 0 2px rgba(43,94,167,.2); }
        .filter-input::placeholder { color:#94a3b8; }
        .btn-calc { display:inline-flex;align-items:center;gap:8px;padding:8px 20px;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;border:0;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(43,94,167,.3);height:36px;white-space:nowrap; }
        .btn-calc:hover { background:linear-gradient(135deg,#1e4b8a,#2b5ea7);box-shadow:0 4px 12px rgba(43,94,167,.4);transform:translateY(-1px); }
        .select2-container--classic .select2-selection--single { height:36px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;transition:all .2s;box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .select2-container--classic .select2-selection--single:hover { border-color:#2b5ea7;box-shadow:0 0 0 2px rgba(43,94,167,.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height:34px;padding-left:10px;padding-right:52px;color:#1e293b;font-size:.8rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height:34px;width:22px;background:transparent;border-left:0;right:0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position:absolute;right:22px;top:50%;transform:translateY(-50%);font-size:16px;font-weight:bold;color:#94a3b8;cursor:pointer;padding:2px 6px;z-index:2;background:#fff;border-radius:50%;line-height:1; }
        .select2-dropdown { font-size:.8rem;border-radius:8px;border:1px solid #cbd5e1;box-shadow:0 8px 24px rgba(0,0,0,.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color:#2b5ea7; }
        .student-summary { padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px; }
        .total-badge { background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px; }
        .applications-btn { display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:13px;font-weight:600;color:#fff;background:linear-gradient(135deg,#2b5ea7,#3b82f6);border-radius:8px;text-decoration:none;transition:opacity .2s; }
        .applications-btn:hover { opacity:.85; }
        .student-table { width:100%;border-collapse:separate;border-spacing:0;font-size:13px; }
        .student-table thead { position:sticky;top:0;z-index:10; }
        .student-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef,#d1d9e6); }
        .student-table th { padding:12px 10px;text-align:left;font-weight:600;font-size:11px;color:#334155;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;border-bottom:2px solid #cbd5e1; }
        .student-table tbody tr { transition:all .15s;border-bottom:1px solid #f1f5f9; }
        .student-table tbody tr:nth-child(even) { background:#f8fafc; }
        .student-table tbody tr:nth-child(odd) { background:#fff; }
        .student-table tbody tr:hover { background:#eff6ff !important;box-shadow:inset 4px 0 0 #2b5ea7; }
        .student-table td { padding:10px;vertical-align:middle;line-height:1.4; }
        .student-name-link { color:#1e40af;font-weight:700; }
        .text-cell { font-size:12.5px;font-weight:500;line-height:1.35;display:block; }
        .text-emerald { color:#047857; }
        .text-cyan { color:#0e7490;max-width:220px;white-space:normal;word-break:break-word; }
        .badge { display:inline-block;padding:3px 9px;border-radius:6px;font-size:11.5px;font-weight:600;line-height:1.4; }
        .badge-violet { background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;white-space:nowrap; }
        .badge-teal { background:#ccfbf1;color:#0f766e;border:1px solid #99f6e4;white-space:nowrap; }
        .badge-indigo { background:linear-gradient(135deg,#1a3268,#2b5ea7);color:#fff;white-space:nowrap; }
        .btn-action { padding:4px 10px;font-size:11px;font-weight:600;border:0;border-radius:6px;cursor:pointer;transition:all .15s;white-space:nowrap; }
        .btn-action:hover { transform:translateY(-1px); }
        .btn-action-green { background:linear-gradient(135deg,#059669,#10b981);color:#fff; }
        .btn-action-green:hover { box-shadow:0 2px 8px rgba(16,185,129,.4); }
    </style>
</x-app-layout>
