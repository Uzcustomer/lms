<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Talabalar reytingi
        </h2>
    </x-slot>

    <style>
        .srf-card { background: #fff; border: 1px solid #e5e9f0; border-radius: 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, .06); padding: 18px 20px 14px; margin-bottom: 24px; }
        .srf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px 16px; align-items: end; }
        .srf-field label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; letter-spacing: .01em; }
        .srf-field select, .srf-field input { width: 100%; height: 40px; box-sizing: border-box; border: 1px solid #d5dbe5; border-radius: 10px;
            background-color: #f8fafc; padding: 0 12px; font-size: 13px; color: #1e293b; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        .srf-field select { padding-right: 34px; }
        .srf-field select:focus, .srf-field input:focus { outline: none; border-color: #2563eb; background-color: #fff; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); }
        .srf-field.is-set select, .srf-field.is-set input { border-color: #93b4f5; background-color: #eef4ff; }
        .srf-search { display: flex; gap: 8px; }
        .srf-search input { flex: 1; min-width: 0; }
        .srf-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #eef1f6; }
        .srf-meta { font-size: 12px; color: #64748b; }
        .srf-meta b { color: #1e293b; }
        .srf-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
        .srf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 40px; padding: 0 16px; border-radius: 10px;
            border: 1px solid transparent; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; transition: filter .15s, transform .15s; white-space: nowrap; }
        .srf-btn:hover { filter: brightness(.95); transform: translateY(-1px); }
        .srf-btn:focus-visible { outline: 2px solid #1d4ed8; outline-offset: 2px; }
        .srf-btn svg { width: 16px; height: 16px; flex: none; }
        .srf-btn-icon { width: 40px; padding: 0; background: #2563eb; color: #fff; }
        .srf-btn-excel { background: #16a34a; color: #fff; }
        .srf-btn-five { background: #f59e0b; color: #fff; box-shadow: 0 2px 6px rgba(245, 158, 11, .35); }
        .srf-btn-reset { background: #fff; color: #475569; border-color: #d5dbe5; }
    </style>

    {{-- Filtrlar --}}
    <div class="srf-card">
        <form method="GET" action="{{ route('admin.student-ratings.index') }}">
            <div class="srf-grid">
                <div class="srf-field {{ $selectedDepartment ? 'is-set' : '' }}">
                    <label for="srf-department">Fakultet</label>
                    <select id="srf-department" name="department" onchange="this.form.submit()">
                        <option value="">Barcha fakultetlar</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->department_code }}" {{ $selectedDepartment == $dept->department_code ? 'selected' : '' }}>{{ $dept->department_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="srf-field {{ $selectedSpecialty ? 'is-set' : '' }}">
                    <label for="srf-specialty">Yo'nalish</label>
                    <select id="srf-specialty" name="specialty" onchange="this.form.submit()">
                        <option value="">Barcha yo'nalishlar</option>
                        @foreach($specialties as $spec)
                            <option value="{{ $spec->specialty_code }}" {{ $selectedSpecialty == $spec->specialty_code ? 'selected' : '' }}>{{ $spec->specialty_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="srf-field {{ $selectedLevel ? 'is-set' : '' }}">
                    <label for="rating-level-select">Kurs</label>
                    <select name="level" id="rating-level-select" onchange="this.form.submit()">
                        <option value="">Barchasi</option>
                        @foreach(['11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs', '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs'] as $code => $label)
                            <option value="{{ $code }}" {{ $selectedLevel == $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="srf-field {{ $selectedSemester ? 'is-set' : '' }}">
                    <label for="rating-semester-select">Semestr</label>
                    <select name="semester" id="rating-semester-select" onchange="this.form.submit()">
                        <option value="">Barchasi</option>
                        @foreach($semesterRows as $sem)
                            <option value="{{ $sem->code }}"
                                    data-level="{{ $sem->level_code }}"
                                    data-current="{{ $sem->current ? '1' : '0' }}"
                                    {{ (string) $selectedSemester === (string) $sem->code ? 'selected' : '' }}>
                                {{ $sem->name }}{{ $sem->current ? ' (joriy)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="srf-field {{ $selectedGroup ? 'is-set' : '' }}">
                    <label for="srf-group">Guruh</label>
                    <select id="srf-group" name="group_name" onchange="this.form.submit()">
                        <option value="">Barchasi</option>
                        @foreach($groups as $g)
                            <option value="{{ $g }}" {{ $selectedGroup === $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="srf-field {{ $selectedSubject ? 'is-set' : '' }}">
                    <label for="srf-subject">Fan</label>
                    <select id="srf-subject" name="subject_id" onchange="this.form.submit()">
                        <option value="">Barcha fanlar</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->subject_id }}" {{ (string) $selectedSubject === (string) $s->subject_id ? 'selected' : '' }}>{{ $s->subject_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="srf-field {{ ($search ?? '') !== '' ? 'is-set' : '' }}">
                    <label for="srf-search">Qidirish</label>
                    <div class="srf-search">
                        <input type="text" id="srf-search" name="search" value="{{ $search ?? '' }}" placeholder="F.I.O yoki guruh...">
                        <button type="submit" class="srf-btn srf-btn-icon" title="Qidirish" aria-label="Qidirish">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="srf-actions">
                <div class="srf-meta">
                    @if($lastUpdated)
                        Oxirgi yangilanish: <b>{{ \Carbon\Carbon::parse($lastUpdated)->format('d.m.Y H:i') }}</b> &middot; Jami: <b>{{ $totalStudents }}</b> ta talaba
                    @endif
                </div>
                <div class="srf-buttons">
                    @if(request()->query())
                        <a href="{{ route('admin.student-ratings.index') }}" class="srf-btn srf-btn-reset">Filtrlarni tozalash</a>
                    @endif
                    <a href="{{ route('admin.student-ratings.export-excel', request()->query()) }}" class="srf-btn srf-btn-excel">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Excel
                    </a>
                    <a href="{{ route('admin.student-ratings.five-candidates') }}" class="srf-btn srf-btn-five">
                        <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        5 ga da'vogarlar
                    </a>
                </div>
            </div>
        </form>
    </div>

    @if($top10->isEmpty())
    <div class="bg-white shadow rounded-lg p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
        </svg>
        <p class="text-gray-500 text-lg">Reyting ma'lumotlari topilmadi.</p>
        <p class="text-gray-400 text-sm mt-2">
            <code>php artisan ratings:calculate</code> buyrug'ini ishga tushiring.
        </p>
    </div>
    @else

    {{-- TOP 10 --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
            <span class="text-yellow-500 text-2xl">&#9733;</span> TOP 10
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($top10 as $index => $r)
            @php
                if ($index === 0) { $border = 'border-yellow-400 bg-yellow-50'; $medal = '🥇'; }
                elseif ($index === 1) { $border = 'border-gray-400 bg-gray-50'; $medal = '🥈'; }
                elseif ($index === 2) { $border = 'border-amber-600 bg-amber-50'; $medal = '🥉'; }
                else { $border = 'border-blue-200 bg-blue-50'; $medal = ''; }

                if ($r->jn_average >= 80) $scoreColor = 'text-green-600';
                elseif ($r->jn_average >= 60) $scoreColor = 'text-yellow-600';
                else $scoreColor = 'text-red-600';
            @endphp
            <button type="button" class="border-2 rounded-xl p-4 cursor-pointer hover:shadow-lg transition-shadow {{ $border }} text-left w-full"
                 onclick="showSubjectsModal('{{ $r->student_hemis_id }}')">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 text-center w-12">
                        @if($medal)
                            <div class="text-3xl">{{ $medal }}</div>
                        @else
                            <div class="w-9 h-9 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center mx-auto text-sm">{{ $index + 1 }}</div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-800 text-sm">{{ $r->full_name }}</div>
                        <div class="text-xs text-gray-400">{{ $r->group_name }} &middot; {{ $r->subjects_count }} fan</div>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <div class="text-2xl font-bold {{ $scoreColor }}">{{ number_format($r->jn_average, 1) }}</div>
                    </div>
                </div>
            </button>
            @endforeach
        </div>
    </div>

    {{-- Qolgan talabalar --}}
    @if($others->total() > 0)
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Boshqa talabalar</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">O'rin</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guruh</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Yo'nalish</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Fanlar</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">JN o'rtacha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($others as $r)
                    @php
                        if ($r->jn_average >= 80) $scoreBg = 'bg-green-100 text-green-800';
                        elseif ($r->jn_average >= 60) $scoreBg = 'bg-yellow-100 text-yellow-800';
                        else $scoreBg = 'bg-red-100 text-red-800';
                    @endphp
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="showSubjectsModal('{{ $r->student_hemis_id }}')">
                        <td class="px-4 py-3 text-sm font-medium text-gray-500">{{ 10 + ($others->currentPage() - 1) * $others->perPage() + $loop->iteration }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $r->full_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $r->group_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 max-w-[200px] truncate">{{ $r->specialty_name }}</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">{{ $r->subjects_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-bold {{ $scoreBg }}">
                                {{ number_format($r->jn_average, 1) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $others->links() }}</div>
    </div>
    @endif
    @endif

    {{-- Modal --}}
    <div id="subjectModal" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,0.5);">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-screen-xl max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b">
                    <h3 id="modalTitle" class="font-bold text-gray-800">Fan tafsilotlari</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <div id="modalBody" class="p-5 overflow-y-auto" style="max-height:60vh;"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function showSubjectsModal(hemisId) {
        document.getElementById('modalBody').innerHTML = '<div class="text-center text-gray-400 py-4">Yuklanmoqda...</div>';
        document.getElementById('subjectModal').classList.remove('hidden');
        fetch('/admin/student-ratings/' + hemisId + '/subjects')
            .then(r => r.json())
            .then(function(data) {
                document.getElementById('modalTitle').textContent = data.full_name + ' — ' + data.group_name;
                document.getElementById('modalBody').innerHTML = buildTable(data.subjects)
                    + '<div class="mt-3 text-right text-sm font-bold text-gray-600">Umumiy o\'rtacha: ' + data.jn_average + '</div>';
            })
            .catch(function() {
                document.getElementById('modalBody').innerHTML = '<div class="text-center text-red-500 py-4">Xatolik yuz berdi</div>';
            });
    }

    function closeModal() {
        document.getElementById('subjectModal').classList.add('hidden');
    }
    document.getElementById('subjectModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    function buildTable(subjects) {
        if (!subjects || !subjects.length) return '<div class="text-center text-gray-400 py-4">Fanlar topilmadi</div>';
        let html = '<table class="w-full text-sm"><thead><tr class="bg-gray-50">'
            + '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500">#</th>'
            + '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Fan nomi</th>'
            + '<th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Kunlar</th>'
            + '<th class="px-3 py-2 text-center text-xs font-medium text-gray-500">JN bali</th>'
            + '</tr></thead><tbody>';
        subjects.forEach(function(s, i) {
            let color = s.average >= 80 ? 'text-green-700 bg-green-50' : (s.average >= 60 ? 'text-yellow-700 bg-yellow-50' : 'text-red-700 bg-red-50');
            html += '<tr class="border-t border-gray-100">'
                + '<td class="px-3 py-2 text-gray-400">' + (i+1) + '</td>'
                + '<td class="px-3 py-2 text-gray-800">' + s.name + '</td>'
                + '<td class="px-3 py-2 text-center text-gray-500">' + s.days + '</td>'
                + '<td class="px-3 py-2 text-center"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold ' + color + '">' + s.average + '</span></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        return html;
    }
    </script>
    @endpush
</x-app-layout>
