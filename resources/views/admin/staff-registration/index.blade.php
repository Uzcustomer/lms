<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Registrator ofisi bo'linmalari
        </h2>
    </x-slot>

    @if (session('success'))
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif
    @if ($errors->any())
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                @foreach ($errors->all() as $error)
                    <span class="block">{{ $error }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">

                {{-- Chap: Yangi biriktirish formasi --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div style="padding: 16px 20px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff;">
                        <h3 style="margin: 0; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Yangi biriktirish
                        </h3>
                    </div>
                    <div style="padding: 16px 20px;">
                        <form action="{{ route('admin.staff-registration.store') }}" method="POST">
                            @csrf
                            <div style="display: flex; flex-direction: column; gap: 12px;">

                                {{-- Bo'linma turi --}}
                                <div>
                                    <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Bo'linma turi <span style="color: #dc2626;">*</span></label>
                                    <select name="division_type" required style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none;">
                                        <option value="">Tanlang...</option>
                                        <option value="front_office" {{ old('division_type') == 'front_office' ? 'selected' : '' }}>Front ofis</option>
                                        <option value="back_office" {{ old('division_type') == 'back_office' ? 'selected' : '' }}>Back ofis</option>
                                    </select>
                                </div>

                                {{-- Xodim --}}
                                <div>
                                    <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Xodim <span style="color: #dc2626;">*</span></label>
                                    @if($registrators->isEmpty())
                                        <div style="padding: 8px; background: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; font-size: 12px; color: #854d0e;">
                                            "Registrator ofisi" roliga ega xodim topilmadi. Avval xodimga <strong>registrator_ofisi</strong> rolini bering.
                                        </div>
                                    @else
                                        <select name="teacher_id" required style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none;">
                                            <option value="">Xodim tanlang...</option>
                                            @foreach($registrators as $reg)
                                                <option value="{{ $reg->id }}" {{ old('teacher_id') == $reg->id ? 'selected' : '' }}>{{ $reg->full_name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>

                                {{-- Fakultetlar (checkbox) --}}
                                <div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                                        <label style="font-size: 12px; font-weight: 600; color: #374151;">Fakultetlar <span style="color: #dc2626;">*</span></label>
                                        <label style="font-size: 11px; color: #6366f1; cursor: pointer; user-select: none; font-weight: 600;" onclick="toggleAllDepartments()">
                                            <span id="toggle-all-dept-label">Barchasini tanlash</span>
                                        </label>
                                    </div>
                                    <div id="departments-checkbox-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px;">
                                        @foreach($departments as $dept)
                                            <label style="display: flex; align-items: center; gap: 6px; padding: 5px 8px; border-radius: 6px; cursor: pointer; font-size: 12px;"
                                                   onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                                                <input type="checkbox" name="department_hemis_ids[]" value="{{ $dept->department_hemis_id }}"
                                                    class="dept-checkbox"
                                                    {{ is_array(old('department_hemis_ids')) && in_array($dept->department_hemis_id, old('department_hemis_ids')) ? 'checked' : '' }}
                                                    style="accent-color: #6366f1; width: 15px; height: 15px;">
                                                <span>{{ $dept->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div id="dept-count" style="font-size: 10px; color: #9ca3af; margin-top: 2px;">0 ta tanlangan</div>
                                </div>

                                {{-- Yo'nalish (faqat 1 ta fakultet tanlanganda ko'rinadi) --}}
                                <div id="specialty-wrapper" style="display: none;">
                                    <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Yo'nalish <span style="font-size: 10px; color: #9ca3af;">(ixtiyoriy)</span></label>
                                    <select name="specialty_hemis_id" id="specialty-select" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none;">
                                        <option value="">Barcha yo'nalishlar</option>
                                    </select>
                                </div>

                                {{-- Kurs --}}
                                <div>
                                    <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Kurs <span style="font-size: 10px; color: #9ca3af;">(ixtiyoriy)</span></label>
                                    <select name="level_code" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none;">
                                        <option value="">Barcha kurslar</option>
                                        @foreach($courses as $course)
                                            <option value="{{ $course->level_code }}" {{ old('level_code') == $course->level_code ? 'selected' : '' }}>{{ $course->level_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" style="width: 100%; padding: 10px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Biriktirish
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- O'ng: Faol biriktirishlar + Tarix --}}
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    {{-- Faol biriktirishlar --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div style="padding: 16px 20px; background: linear-gradient(135deg, #0ea5e9, #06b6d4); color: #fff; display: flex; align-items: center; justify-content: space-between;">
                            <h3 style="margin: 0; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Faol biriktirishlar
                            </h3>
                            <span style="font-size: 12px; background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 12px;">{{ $activeDivisions->count() }} ta</span>
                        </div>
                        <div style="padding: 0;">
                            @if($activeDivisions->isEmpty())
                                <div style="padding: 40px 20px; text-align: center; color: #9ca3af;">
                                    <svg style="width: 48px; height: 48px; margin: 0 auto 12px; opacity: 0.4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <p style="font-size: 14px; font-weight: 600;">Hali biriktirish yo'q</p>
                                    <p style="font-size: 12px;">Chap formadan yangi biriktirish qo'shing</p>
                                </div>
                            @else
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                        <thead>
                                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                                <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase;">Bo'linma</th>
                                                <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase;">Xodim</th>
                                                <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase;">Fakultet</th>
                                                <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase;">Yo'nalish</th>
                                                <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase;">Kurs</th>
                                                <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase;">Boshlanish</th>
                                                <th style="padding: 10px 12px; text-align: center; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase;">Amal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($activeDivisions as $division)
                                                <tr style="border-bottom: 1px solid #f1f5f9;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                                    <td style="padding: 8px 12px;">
                                                        @if($division->division_type === 'front_office')
                                                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; background: #dbeafe; color: #1d4ed8; border-radius: 12px; font-size: 11px; font-weight: 600;">Front</span>
                                                        @else
                                                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 11px; font-weight: 600;">Back</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding: 8px 12px; font-weight: 600; color: #1e293b; font-size: 12px;">{{ $division->teacher->full_name ?? '-' }}</td>
                                                    <td style="padding: 8px 12px; color: #475569; font-size: 12px;">{{ $division->department->name ?? '-' }}</td>
                                                    <td style="padding: 8px 12px; color: #475569; font-size: 12px;">{{ $division->specialty->name ?? 'Barchasi' }}</td>
                                                    <td style="padding: 8px 12px; color: #475569; font-size: 12px;">{{ $division->level_name ?? 'Barchasi' }}</td>
                                                    <td style="padding: 8px 12px; color: #64748b; font-size: 11px;">{{ $division->started_at ? $division->started_at->format('d.m.Y') : '-' }}</td>
                                                    <td style="padding: 8px 12px; text-align: center;">
                                                        <form action="{{ route('admin.staff-registration.destroy', $division) }}" method="POST" onsubmit="return confirm('Bu biriktirishni tugatishni tasdiqlaysizmi? Tarixga o\'tkaziladi.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" style="padding: 3px 8px; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; font-size: 10px; font-weight: 600; cursor: pointer;">Tugatish</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tarix --}}
                    @if($historyDivisions->isNotEmpty())
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" type="button" style="width: 100%; padding: 14px 20px; background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; display: flex; align-items: center; justify-content: space-between; border: none; cursor: pointer;">
                            <h3 style="margin: 0; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Tarix
                                <span style="font-size: 11px; background: rgba(255,255,255,0.2); padding: 1px 8px; border-radius: 10px;">{{ $historyDivisions->count() }} ta</span>
                            </h3>
                            <svg :style="open ? 'transform: rotate(180deg)' : ''" style="width: 16px; height: 16px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-transition style="padding: 0;">
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                    <thead>
                                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                            <th style="padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 10px; text-transform: uppercase;">Bo'linma</th>
                                            <th style="padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 10px; text-transform: uppercase;">Xodim</th>
                                            <th style="padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 10px; text-transform: uppercase;">Fakultet</th>
                                            <th style="padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 10px; text-transform: uppercase;">Yo'nalish</th>
                                            <th style="padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 10px; text-transform: uppercase;">Kurs</th>
                                            <th style="padding: 8px 12px; text-align: left; font-weight: 600; color: #475569; font-size: 10px; text-transform: uppercase;">Davr</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($historyDivisions as $division)
                                            <tr style="border-bottom: 1px solid #f1f5f9; opacity: 0.7;">
                                                <td style="padding: 6px 12px;">
                                                    @if($division->division_type === 'front_office')
                                                        <span style="padding: 2px 8px; background: #e0e7ff; color: #4338ca; border-radius: 10px; font-size: 10px; font-weight: 600;">Front</span>
                                                    @else
                                                        <span style="padding: 2px 8px; background: #fef3c7; color: #92400e; border-radius: 10px; font-size: 10px; font-weight: 600;">Back</span>
                                                    @endif
                                                </td>
                                                <td style="padding: 6px 12px; font-weight: 600; color: #64748b;">{{ $division->teacher->full_name ?? '-' }}</td>
                                                <td style="padding: 6px 12px; color: #64748b;">{{ $division->department->name ?? '-' }}</td>
                                                <td style="padding: 6px 12px; color: #64748b;">{{ $division->specialty->name ?? 'Barchasi' }}</td>
                                                <td style="padding: 6px 12px; color: #64748b;">{{ $division->level_name ?? 'Barchasi' }}</td>
                                                <td style="padding: 6px 12px; color: #64748b; font-size: 11px;">
                                                    {{ $division->started_at ? $division->started_at->format('d.m.Y') : '?' }}
                                                    &rarr;
                                                    {{ $division->ended_at ? $division->ended_at->format('d.m.Y') : '?' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    <script>
        // Fakultetlar checkbox - barchasini tanlash/bekor qilish
        function toggleAllDepartments() {
            var checkboxes = document.querySelectorAll('.dept-checkbox');
            var allChecked = true;
            checkboxes.forEach(function(cb) { if (!cb.checked) allChecked = false; });

            checkboxes.forEach(function(cb) { cb.checked = !allChecked; });
            updateDeptCount();
        }

        function updateDeptCount() {
            var checkedBoxes = document.querySelectorAll('.dept-checkbox:checked');
            var checked = checkedBoxes.length;
            var total = document.querySelectorAll('.dept-checkbox').length;
            document.getElementById('dept-count').textContent = checked + ' / ' + total + ' ta tanlangan';
            var label = document.getElementById('toggle-all-dept-label');
            label.textContent = (checked === total) ? 'Barchasini bekor qilish' : 'Barchasini tanlash';

            // Yo'nalish: faqat 1 ta fakultet tanlanganda ko'rsatish
            var specialtyWrapper = document.getElementById('specialty-wrapper');
            var specialtySelect = document.getElementById('specialty-select');
            if (checked === 1) {
                specialtyWrapper.style.display = 'block';
                loadSpecialties(checkedBoxes[0].value);
            } else {
                specialtyWrapper.style.display = 'none';
                specialtySelect.innerHTML = '<option value="">Barcha yo\'nalishlar</option>';
                specialtySelect.value = '';
            }
        }

        // Har bir checkbox o'zgarganda hisobni yangilash
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.dept-checkbox').forEach(function(cb) {
                cb.addEventListener('change', updateDeptCount);
            });
            updateDeptCount();
        });

        // Yo'nalishlarni AJAX bilan yuklash (bitta fakultet tanlanganda)
        function loadSpecialties(departmentHemisId) {
            var select = document.getElementById('specialty-select');
            select.innerHTML = '<option value="">Barcha yo\'nalishlar</option>';

            if (!departmentHemisId) return;

            fetch('{{ route("admin.staff-registration.specialties") }}?department_hemis_id=' + departmentHemisId, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(specialties) {
                specialties.forEach(function(specialty) {
                    var option = document.createElement('option');
                    option.value = specialty.specialty_hemis_id;
                    option.textContent = specialty.name;
                    select.appendChild(option);
                });
            })
            .catch(function(error) {
                console.error('Yo\'nalishlarni yuklashda xatolik:', error);
            });
        }
    </script>
</x-app-layout>
