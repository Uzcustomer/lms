<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Talabalar</h2>
    </x-slot>

    <style>
        .tutor-container { max-width: 100%; margin: 0 auto; padding: 16px; }
        .group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
        .group-card {
            display: flex; align-items: center; gap: 14px; padding: 16px 18px;
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px;
            cursor: pointer; transition: all 0.2s; text-decoration: none;
        }
        .group-card:hover { border-color: #3b82f6; box-shadow: 0 4px 16px rgba(43,94,167,0.12); transform: translateY(-2px); }
        .group-card.active { border-color: #2b5ea7; background: #eff6ff; box-shadow: 0 2px 12px rgba(43,94,167,0.15); }
        .group-icon {
            width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #2b5ea7, #3b82f6); flex-shrink: 0;
        }
        .group-name { font-size: 15px; font-weight: 700; color: #1e293b; }
        .group-count { font-size: 12px; color: #64748b; margin-top: 2px; }
        .group-badge { padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; background: #dbeafe; color: #1e40af; }

        .back-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
            font-size: 13px; font-weight: 600; color: #2b5ea7; background: #eff6ff;
            border: 1px solid #bfdbfe; border-radius: 10px; cursor: pointer; transition: all 0.2s; text-decoration: none; margin-bottom: 12px;
        }
        .back-btn:hover { background: #dbeafe; }

        .student-list { margin-top: 8px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 8px; }
        .student-item {
            display: flex; align-items: center; gap: 10px; padding: 12px 14px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; transition: all 0.15s;
        }
        .student-item:hover { background: #f0f7ff; }
        .student-avatar {
            width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0;
            background: linear-gradient(135deg, #94a3b8, #64748b);
        }
        .student-avatar img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
        .student-name { font-size: 13px; font-weight: 600; color: #1e293b; }
        .student-id { font-size: 10px; color: #94a3b8; font-family: monospace; }
        .student-meta { font-size: 10px; color: #64748b; margin-top: 1px; }
        .student-right { margin-left: auto; text-align: right; flex-shrink: 0; }
        .student-gpa { font-size: 12px; font-weight: 700; }
        .student-status { font-size: 9px; padding: 2px 6px; border-radius: 5px; font-weight: 600; }

        .search-box {
            width: 100%; padding: 10px 14px 10px 38px; border: 1.5px solid #e2e8f0; border-radius: 12px;
            font-size: 14px; background: #fff; outline: none; transition: all 0.2s;
        }
        .search-box:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .search-wrap { position: relative; margin-bottom: 12px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
        .empty-state svg { width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1; }

        @media (max-width: 1024px) {
            .student-list { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .tutor-container { padding: 8px; }
            .group-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .group-card { padding: 10px 12px; gap: 10px; }
            .group-icon { width: 34px; height: 34px; border-radius: 10px; }
            .group-icon svg { width: 18px; height: 18px; }
            .group-name { font-size: 13px; }
            .group-count { font-size: 11px; }
            .group-badge { font-size: 11px; padding: 3px 8px; }
            .tutor-container { padding: 0; }
            .student-list { grid-template-columns: 1fr; gap: 4px; padding: 4px; }
            .student-item { padding: 6px 8px; gap: 6px; border-radius: 8px; }
            .student-avatar, .student-avatar img { width: 28px; height: 28px; font-size: 11px; }
            .student-name { font-size: 12px; }
            .student-id { font-size: 9px; }
            .student-meta { font-size: 9px; }
            .student-gpa { font-size: 11px; }
            .student-status { font-size: 8px; padding: 1px 5px; }
            .search-box { padding: 8px 12px 8px 34px; font-size: 13px; border-radius: 10px; }
            .back-btn { padding: 6px 12px; font-size: 12px; }
        }
    </style>

    <div class="tutor-container">

        {{-- Guruhni tanlash ko'rinishi --}}
        @if(!request('group'))
        <div>
            <div style="margin-bottom: 16px;">
                <h3 style="font-size: 16px; font-weight: 700; color: #1e293b;">Guruhlaringiz</h3>
                <p style="font-size: 13px; color: #64748b;">Talabalarni ko'rish uchun guruhni tanlang</p>
            </div>
            <div class="group-grid">
                @foreach($tutorGroups as $group)
                    @php
                        $studentCount = \App\Models\Student::where('group_id', $group->group_hemis_id)->count();
                    @endphp
                    <a href="{{ route('teacher.students', ['group' => $group->group_hemis_id]) }}"
                       class="group-card">                        <div class="group-icon">
                            <svg style="width:22px;height:22px;color:#fff;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="group-name">{{ $group->name }}</div>
                            <div class="group-count">{{ $studentCount }} ta talaba</div>
                        </div>
                        <span class="group-badge">{{ $studentCount }}</span>
                    </a>
                @endforeach
            </div>

            @if($tutorGroups->isEmpty())
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772"/></svg>
                    <p style="font-size: 14px; font-weight: 600;">Sizga biriktirilgan guruhlar yo'q</p>
                </div>
            @endif
        </div>

        @else
        {{-- Talabalar ro'yxati --}}
        <div>
            <a href="{{ route('teacher.students') }}" class="back-btn">                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                Guruhlar
            </a>

                @php
                    $currentGroup = $tutorGroups->firstWhere('group_hemis_id', request('group'));
                @endphp
                @if($currentGroup)
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                        <div>
                            <h3 style="font-size:18px;font-weight:700;color:#1e293b;">{{ $currentGroup->name }}</h3>
                            <p style="font-size:13px;color:#64748b;">{{ $students->total() }} ta talaba</p>
                        </div>
                    </div>
                @endif

                <div class="search-wrap">
                    <svg class="search-icon" style="width:18px;height:18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" class="search-box" placeholder="Talaba qidirish..." id="student-search"
                           value="{{ request('search') }}" onkeyup="filterStudents(this.value)">
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="student-list">
                        @forelse($students as $index => $student)
                            @php
                                $studentPhoto = \App\Models\StudentPhoto::where('student_id_number', $student->student_id_number)->latest()->first();
                            @endphp
                            <div class="student-item" data-name="{{ mb_strtolower($student->full_name) }}" data-id="{{ $student->student_id_number }}"
                                 onclick="openPhotoModal('{{ $student->id }}', '{{ addslashes($student->full_name) }}', '{{ $student->student_id_number }}', '{{ $student->group_name }}', '{{ $studentPhoto ? asset('storage/' . $studentPhoto->photo_path) : '' }}')"
                                 style="cursor:pointer;">
                                <div style="font-size:12px;color:#94a3b8;width:24px;text-align:center;flex-shrink:0;">{{ $students->firstItem() + $index }}</div>
                                @if($studentPhoto)
                                    <div class="student-avatar"><img src="{{ asset('storage/' . $studentPhoto->photo_path) }}" alt=""></div>
                                @elseif($student->image)
                                    <div class="student-avatar"><img src="{{ $student->image }}" alt=""></div>
                                @else
                                    <div class="student-avatar">{{ mb_substr($student->full_name, 0, 1) }}</div>
                                @endif
                                <div style="flex:1;min-width:0;">
                                    <div class="student-name">{{ $student->full_name }}</div>
                                    <div class="student-id">{{ $student->student_id_number }}</div>
                                    <div class="student-meta">
                                        {{ $student->province_name ?? '' }}{{ $student->phone ? ' · ' . $student->phone : '' }}
                                    </div>
                                </div>
                                <div class="student-right">
                                    @if($student->avg_gpa)
                                        <div class="student-gpa" style="color:{{ $student->avg_gpa >= 3.5 ? '#16a34a' : ($student->avg_gpa >= 2.5 ? '#ca8a04' : '#dc2626') }};">
                                            {{ number_format($student->avg_gpa, 2) }}
                                        </div>
                                    @endif
                                    @if($studentPhoto)
                                        <span class="student-status" style="background:#dbeafe;color:#1e40af;">Rasm bor</span>
                                    @elseif($student->student_status_code == '11' || $student->student_status_name == 'Faol')
                                        <span class="student-status" style="background:#dcfce7;color:#166534;">Faol</span>
                                    @elseif($student->student_status_name)
                                        <span class="student-status" style="background:#fef3c7;color:#92400e;">{{ $student->student_status_name }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="empty-state">
                                <p style="font-size:14px;">Bu guruhda talabalar topilmadi</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                @if($students->hasPages())
                    <div style="padding:12px 0;">
                        {{ $students->appends(request()->query())->links('pagination::tailwind') }}
                    </div>
                @endif
        </div>
        @endif
    </div>

    {{-- Photo Modal --}}
    <div id="photo-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);display:none;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:16px;width:100%;max-width:400px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div id="modal-name" style="font-size:15px;font-weight:700;color:#1e293b;"></div>
                    <div id="modal-info" style="font-size:12px;color:#64748b;margin-top:2px;"></div>
                </div>
                <button onclick="closePhotoModal()" style="width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:18px;color:#64748b;">&times;</button>
            </div>
            <div style="padding:20px;text-align:center;">
                <div id="modal-photo-frame" style="width:100%;aspect-ratio:3/4;border-radius:12px;border:2px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f8fafc;">
                    <div id="modal-no-photo" style="color:#94a3b8;font-size:13px;">
                        <svg style="width:48px;height:48px;margin:0 auto 8px;color:#cbd5e1;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                        Rasm yuklanmagan
                    </div>
                    <img id="modal-photo-img" style="width:100%;height:100%;object-fit:cover;display:none;" alt="">
                </div>
            </div>
            <div style="padding:0 20px 20px;">
                <form id="photo-upload-form" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" id="photo-input" name="photo" accept="image/*" capture="environment" style="display:none;" onchange="previewPhoto(this)">
                    <button type="button" onclick="document.getElementById('photo-input').click()"
                            style="width:100%;padding:12px;background:linear-gradient(135deg,#2b5ea7,#3b82f6);color:#fff;font-size:14px;font-weight:600;border:none;border-radius:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                        <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                        Rasmga olish
                    </button>
                    <button type="submit" id="photo-save-btn" style="display:none;width:100%;padding:12px;margin-top:8px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-size:14px;font-weight:600;border:none;border-radius:12px;cursor:pointer;">
                        Saqlash
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openPhotoModal(studentId, name, idNumber, groupName, photoUrl) {
            document.getElementById('modal-name').textContent = name;
            document.getElementById('modal-info').textContent = idNumber + ' · ' + groupName;
            document.getElementById('photo-upload-form').action = '/teacher/students/' + studentId + '/upload-photo';

            var img = document.getElementById('modal-photo-img');
            var noPhoto = document.getElementById('modal-no-photo');
            if (photoUrl) {
                img.src = photoUrl;
                img.style.display = 'block';
                noPhoto.style.display = 'none';
            } else {
                img.style.display = 'none';
                noPhoto.style.display = 'block';
            }
            document.getElementById('photo-save-btn').style.display = 'none';
            document.getElementById('photo-input').value = '';

            var modal = document.getElementById('photo-modal');
            modal.style.display = 'flex';
        }

        function closePhotoModal() {
            document.getElementById('photo-modal').style.display = 'none';
        }

        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = document.getElementById('modal-photo-img');
                    img.src = e.target.result;
                    img.style.display = 'block';
                    document.getElementById('modal-no-photo').style.display = 'none';
                    document.getElementById('photo-save-btn').style.display = 'block';
                    document.getElementById('modal-photo-frame').style.borderStyle = 'solid';
                    document.getElementById('modal-photo-frame').style.borderColor = '#10b981';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.getElementById('photo-modal').addEventListener('click', function(e) {
            if (e.target === this) closePhotoModal();
        });

        function filterStudents(query) {
            query = query.toLowerCase().replace(/[\/\-\s]/g, '');
            document.querySelectorAll('.student-item').forEach(function(el) {
                var name = (el.dataset.name || '').replace(/[\/\-\s]/g, '');
                var id = (el.dataset.id || '').replace(/[\/\-\s]/g, '');
                el.style.display = (name.indexOf(query) > -1 || id.indexOf(query) > -1) ? '' : 'none';
            });
        }
    </script>
</x-teacher-app-layout>
