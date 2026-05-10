<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Talabalar</h2>
    </x-slot>

    <style>
        .tutor-container { max-width: 100%; margin: 0 auto; padding: 16px; }
        .group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
        .photo-stat-card {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 14px 10px; background: #fff; border: 2px solid #e2e8f0; border-radius: 12px;
            text-decoration: none; transition: all 0.2s; text-align: center;
        }
        .photo-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
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
            .photo-stat-card { grid-template-columns: 1fr; }
            .photo-stat-card div:first-child { font-size: 18px !important; }
            .photo-stat-card div:last-child { font-size: 10px !important; }
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
            .photo-modal-box { max-width: 400px !important; }
            .photo-modal-box > div:first-child { padding: 10px 14px !important; }
            .photo-modal-box > div:first-child #modal-name { font-size: 13px !important; }
            .photo-modal-box > div:first-child #modal-info { font-size: 10px !important; margin-top: 1px !important; }
            .photo-modal-box > div:nth-child(2) { padding: 10px !important; }
            #modal-photo-frame { min-height: 200px !important; max-height: 45vh !important; border-radius: 8px !important; }
            #modal-photo-img { max-height: 45vh !important; }
            #modal-rejection-banner { margin-top: 6px !important; padding: 6px 10px !important; }
            #modal-rejection-banner div:first-child { font-size: 10px !important; margin-bottom: 1px !important; }
            #modal-rejection-banner #modal-rejection-reason { font-size: 10px !important; }
            .photo-modal-box > div:last-child { padding: 0 10px 10px !important; }
            .photo-modal-box #photo-delete-wrap button { padding: 7px !important; font-size: 11px !important; border-radius: 8px !important; }
            .photo-modal-box #photo-file-btn { padding: 8px !important; font-size: 12px !important; margin-bottom: 5px !important; border-radius: 8px !important; }
            .photo-modal-box #photo-capture-btn { padding: 8px !important; font-size: 12px !important; border-radius: 8px !important; }
            .photo-modal-box #photo-save-btn { padding: 8px !important; font-size: 12px !important; border-radius: 8px !important; }
            .photo-modal-box #photo-retake-btn { padding: 6px !important; font-size: 11px !important; margin-top: 4px !important; }
            #photo-modal { padding: 8px !important; }
        }
    </style>

    <div class="tutor-container">

        @php $isNazoratchi = is_active_nazoratchi(); @endphp
        {{-- Guruhni tanlash ko'rinishi --}}
        @if(!request('group') && !request('photo_filter'))
        <div>
            @php
                $ps = $photoStats ?? ['has_photo' => 0, 'approved' => 0, 'rejected' => 0];
            @endphp
            @if(!$isNazoratchi)
            <div style="margin-bottom:16px;">
                <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:10px;">Talaba rasmlari</h3>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                    <a href="{{ route('teacher.students', ['photo_filter' => 'has_photo']) }}" class="photo-stat-card" style="border-color:#bfdbfe;">
                        <div style="font-size:22px;font-weight:800;color:#1e40af;">{{ $ps['has_photo'] }}</div>
                        <div style="font-size:11px;color:#3b82f6;font-weight:600;">Rasm bor</div>
                    </a>
                    <a href="{{ route('teacher.students', ['photo_filter' => 'approved']) }}" class="photo-stat-card" style="border-color:#a7f3d0;">
                        <div style="font-size:22px;font-weight:800;color:#166534;">{{ $ps['approved'] }}</div>
                        <div style="font-size:11px;color:#16a34a;font-weight:600;">Tasdiqlangan</div>
                    </a>
                    <a href="{{ route('teacher.students', ['photo_filter' => 'rejected']) }}" class="photo-stat-card" style="border-color:#fecaca;">
                        <div style="font-size:22px;font-weight:800;color:#dc2626;">{{ $ps['rejected'] }}</div>
                        <div style="font-size:11px;color:#dc2626;font-weight:600;">Rad etilgan</div>
                    </a>
                </div>
            </div>
            @endif

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
                Orqaga
            </a>

                @php
                    $currentGroup = $tutorGroups->firstWhere('group_hemis_id', request('group'));
                    $photoFilterLabel = match(request('photo_filter')) {
                        'has_photo' => 'Rasm bor',
                        'approved' => 'Tasdiqlangan',
                        'rejected' => 'Rad etilgan',
                        default => null,
                    };
                @endphp
                @if($photoFilterLabel)
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                        <div>
                            <h3 style="font-size:18px;font-weight:700;color:#1e293b;">{{ $photoFilterLabel }}</h3>
                            <p style="font-size:13px;color:#64748b;">{{ $students->total() }} ta talaba</p>
                        </div>
                    </div>
                @elseif($currentGroup)
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

                @if(request('photo_filter'))
                {{-- Guruh bo'yicha guruhlangan ko'rinish --}}
                @php
                    $groupedStudents = $students->getCollection()->groupBy('group_name');
                @endphp
                @forelse($groupedStudents as $groupName => $groupStudentsList)
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" style="margin-bottom:12px;">
                        <div style="padding:10px 16px;background:linear-gradient(135deg,#1a3268,#2b5ea7);display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:#fff;font-size:13px;font-weight:700;">{{ $groupName }}</span>
                            <span style="background:rgba(255,255,255,0.2);color:#fff;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;">{{ $groupStudentsList->count() }}</span>
                        </div>
                        <div class="student-list">
                            @foreach($groupStudentsList as $student)
                                @php
                                    $studentPhoto = $isNazoratchi ? null : \App\Models\StudentPhoto::where('student_id_number', $student->student_id_number)->latest()->first();
                                @endphp
                                <div class="student-item" data-name="{{ mb_strtolower($student->full_name) }}" data-id="{{ $student->student_id_number }}"
                                     @if(!$isNazoratchi) onclick="openPhotoModal({{ $student->id }}, {{ json_encode($student->full_name) }}, {{ json_encode($student->student_id_number) }}, {{ json_encode($student->group_name) }}, {{ json_encode($studentPhoto ? asset($studentPhoto->photo_path) : '') }}, {{ json_encode($studentPhoto->status ?? '') }}, {{ json_encode($studentPhoto->rejection_reason ?? '') }})" @endif
                                     style="{{ $isNazoratchi ? '' : 'cursor:pointer;' }}">
                                    @if($studentPhoto && $studentPhoto->photo_path)
                                        <div class="student-avatar"><img src="{{ asset($studentPhoto->photo_path) }}" alt="" style="object-fit:cover;width:100%;height:100%;border-radius:50%;"></div>
                                    @elseif($student->image)
                                        <div class="student-avatar"><img src="{{ $student->image }}" alt=""></div>
                                    @else
                                        <div class="student-avatar">{{ mb_substr($student->full_name, 0, 1) }}</div>
                                    @endif
                                    <div style="flex:1;min-width:0;">
                                        <div class="student-name">{{ $student->full_name }}</div>
                                        <div class="student-id">{{ $student->student_id_number }}</div>
                                    </div>
                                    @if(!$isNazoratchi)
                                    <div class="student-right" style="text-align:right;">
                                        @if($studentPhoto && $studentPhoto->status === 'rejected')
                                            <span class="student-status" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;">Rad etildi</span>
                                            @if($studentPhoto->rejection_reason)
                                                <div style="font-size:10px;color:#991b1b;max-width:140px;margin-top:2px;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $studentPhoto->rejection_reason }}">{{ $studentPhoto->rejection_reason }}</div>
                                            @endif
                                        @elseif($studentPhoto && $studentPhoto->status === 'approved' && $studentPhoto->descriptor_confirmed_at)
                                            <span class="student-status" style="background:#dcfce7;color:#166534;">Tasdiqlangan</span>
                                        @elseif($studentPhoto && $studentPhoto->status === 'approved')
                                            <span class="student-status" style="background:#e0e7ff;color:#3730a3;">Moodle'da ishlanmoqda</span>
                                        @elseif($studentPhoto)
                                            <span class="student-status" style="background:#dbeafe;color:#1e40af;">Rasm bor</span>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="empty-state"><p style="font-size:14px;">Talabalar topilmadi</p></div>
                @endforelse

                @else
                {{-- Oddiy ro'yxat (guruh ichidan) --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="student-list">
                        @forelse($students as $index => $student)
                            @php
                                $studentPhoto = $isNazoratchi ? null : \App\Models\StudentPhoto::where('student_id_number', $student->student_id_number)->latest()->first();
                            @endphp
                            <div class="student-item" data-name="{{ mb_strtolower($student->full_name) }}" data-id="{{ $student->student_id_number }}"
                                 @if(!$isNazoratchi) onclick="openPhotoModal({{ $student->id }}, {{ json_encode($student->full_name) }}, {{ json_encode($student->student_id_number) }}, {{ json_encode($student->group_name) }}, {{ json_encode($studentPhoto ? asset($studentPhoto->photo_path) : '') }}, {{ json_encode($studentPhoto->status ?? '') }}, {{ json_encode($studentPhoto->rejection_reason ?? '') }})" @endif
                                 style="{{ $isNazoratchi ? '' : 'cursor:pointer;' }}">
                                <div style="font-size:10px;color:#b0b8c4;width:16px;text-align:center;flex-shrink:0;">{{ $students->firstItem() + $index }}</div>
                                @if($student->image)
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
                                <div class="student-right" style="text-align:right;">
                                    @if(!$isNazoratchi && $studentPhoto && $studentPhoto->status === 'rejected')
                                        <span class="student-status" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;">Rad etildi</span>
                                        @if($studentPhoto->rejection_reason)
                                            <div style="font-size:10px;color:#991b1b;max-width:160px;margin-top:3px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $studentPhoto->rejection_reason }}">{{ $studentPhoto->rejection_reason }}</div>
                                        @endif
                                    @elseif(!$isNazoratchi && $studentPhoto && $studentPhoto->status === 'approved' && $studentPhoto->descriptor_confirmed_at)
                                        <span class="student-status" style="background:#dcfce7;color:#166534;">Tasdiqlangan</span>
                                    @elseif(!$isNazoratchi && $studentPhoto && $studentPhoto->status === 'approved')
                                        <span class="student-status" style="background:#e0e7ff;color:#3730a3;">Moodle'da ishlanmoqda</span>
                                    @elseif(!$isNazoratchi && $studentPhoto)
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
                @endif {{-- end photo_filter / else --}}
        </div>
        @endif
    </div>

    {{-- Fullscreen Camera --}}
    {{--
        Overlay 3:4 safe-zone va body-shape ko'rsatkichini birlashtiradi.
        Video object-fit:cover bilan to'liq ekranga cho'ziladi, ammo snapPhoto()
        markazdan 3:4 ga qirqadi — overlay aynan o'sha qirqilgan zonani ko'rsatadi.
        Yuz balandligi ramka balandligining ~38% i — bu Moodle face-api'ning 35%
        chegarasidan biroz yuqorida xavfsizlik marjasi sifatida tanlangan.
    --}}
    <div id="camera-fullscreen" style="display:none;position:fixed;inset:0;z-index:99999;background:#000;">
        <video id="camera-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
        <svg viewBox="0 0 300 400" preserveAspectRatio="xMidYMid meet"
             style="position:absolute;top:50%;left:50%;height:100%;aspect-ratio:3/4;transform:translate(-50%,-50%);pointer-events:none;">
            {{-- Tashqarini xiraytirish (3:4 ramka tashqarisi) --}}
            <defs>
                <mask id="camMask">
                    <rect width="300" height="400" fill="#fff"/>
                    <rect x="0" y="0" width="300" height="400" fill="#000"/>
                </mask>
            </defs>
            {{-- 3:4 safe-zone chegarasi --}}
            <rect x="2" y="2" width="296" height="396" fill="none" stroke="#fff" stroke-width="2" stroke-dasharray="8 6" opacity="0.5" rx="4"/>
            {{-- Bosh silueti — markazda, ramka tepasidan 12% pastda. Yuz balandligi ~150px / 400px = 37.5% --}}
            <ellipse cx="150" cy="135" rx="56" ry="72" fill="none" stroke="#22c55e" stroke-width="3" opacity="0.95"/>
            {{-- Bo'yin --}}
            <path d="M126 200 Q126 215 118 224" fill="none" stroke="#22c55e" stroke-width="2.5" opacity="0.85"/>
            <path d="M174 200 Q174 215 182 224" fill="none" stroke="#22c55e" stroke-width="2.5" opacity="0.85"/>
            {{-- Yelkalar (kengroq, oq xalat siluetiga mos) --}}
            <path d="M118 224 Q72 238 38 268 Q18 290 8 330 L0 400" fill="none" stroke="#22c55e" stroke-width="2.5" opacity="0.7"/>
            <path d="M182 224 Q228 238 262 268 Q282 290 292 330 L300 400" fill="none" stroke="#22c55e" stroke-width="2.5" opacity="0.7"/>
            {{-- Markaz nuqtasi (yuz markazi) --}}
            <circle cx="150" cy="135" r="2" fill="#fbbf24" opacity="0.9"/>
            {{-- Tepa marjin yo'l-yo'rig'i — bosh ustida 12% bo'sh joy bo'lsin --}}
            <line x1="150" y1="2" x2="150" y2="62" stroke="#fbbf24" stroke-width="1" stroke-dasharray="3 4" opacity="0.6"/>
            <text x="155" y="35" font-size="9" fill="#fbbf24" opacity="0.85">~10% bo'sh</text>
        </svg>
        {{-- Yo'l-yo'riq paneli --}}
        <div style="position:absolute;top:16px;left:16px;right:16px;padding:10px 12px;background:rgba(0,0,0,0.55);border-radius:10px;backdrop-filter:blur(6px);">
            <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:4px;">Talabani yashil siluetga moslang</div>
            <div style="font-size:11px;color:#cbd5e1;line-height:1.4;">
                Bosh — markazda, yelkalargacha ko'rinib tursin.<br>
                Oq xalat, oq fon, yorug'lik yuzga teng tushsin.
            </div>
        </div>
        <div style="position:absolute;bottom:0;left:0;right:0;padding:20px;display:flex;align-items:center;justify-content:center;gap:16px;background:linear-gradient(transparent,rgba(0,0,0,0.7));">
            <button type="button" onclick="closeCameraFullscreen()" style="width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,0.2);border:2px solid rgba(255,255,255,0.5);color:#fff;font-size:20px;cursor:pointer;backdrop-filter:blur(4px);">&times;</button>
            <button type="button" onclick="snapPhoto()" style="width:72px;height:72px;border-radius:50%;background:#fff;border:4px solid rgba(255,255,255,0.5);cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#059669,#10b981);margin:auto;"></div>
            </button>
            <div style="width:50px;"></div>
        </div>
    </div>

    {{-- Photo Modal — nazoratchi uchun ko'rsatilmaydi --}}
    @if(!$isNazoratchi)
    <div id="photo-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:16px;">
        <div class="photo-modal-box" style="background:#fff;border-radius:16px;width:100%;max-width:640px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div id="modal-name" style="font-size:15px;font-weight:700;color:#1e293b;"></div>
                    <div id="modal-info" style="font-size:12px;color:#64748b;margin-top:2px;"></div>
                </div>
                <button onclick="closePhotoModal()" style="width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:18px;color:#64748b;">&times;</button>
            </div>
            <div style="padding:20px;text-align:center;">
                <div id="modal-photo-frame" style="width:100%;min-height:400px;max-height:70vh;border-radius:12px;border:2px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#1e293b;position:relative;">
                    {{-- Placeholder + body-shape yo'l-yo'rig'i (rasm yo'q paytda) --}}
                    <div id="modal-no-photo" style="color:#94a3b8;font-size:13px;z-index:2;text-align:center;padding:20px;">
                        <svg style="width:160px;height:213px;margin:0 auto 12px;display:block;" viewBox="0 0 300 400">
                            <rect x="2" y="2" width="296" height="396" fill="none" stroke="#475569" stroke-width="2" stroke-dasharray="8 6" rx="4"/>
                            <ellipse cx="150" cy="135" rx="56" ry="72" fill="none" stroke="#64748b" stroke-width="3"/>
                            <path d="M126 200 Q126 215 118 224" fill="none" stroke="#64748b" stroke-width="2.5"/>
                            <path d="M174 200 Q174 215 182 224" fill="none" stroke="#64748b" stroke-width="2.5"/>
                            <path d="M118 224 Q72 238 38 268 Q18 290 8 330 L0 400" fill="none" stroke="#64748b" stroke-width="2.5" opacity="0.7"/>
                            <path d="M182 224 Q228 238 262 268 Q282 290 292 330 L300 400" fill="none" stroke="#64748b" stroke-width="2.5" opacity="0.7"/>
                        </svg>
                        Rasm yuklanmagan<br>
                        <span style="font-size:11px;color:#64748b;">Talabani yashil siluetga mos qilib oling</span>
                    </div>
                    {{-- Tushirilgan rasm --}}
                    <img id="modal-photo-img" style="max-width:100%;max-height:70vh;width:auto;height:auto;object-fit:contain;display:none;z-index:1;" alt="">
                    {{-- Preview overlay — rasm tanlangandan keyin tutor sifatni ko'z bilan tekshiradi --}}
                    <svg id="modal-photo-overlay" viewBox="0 0 300 400" preserveAspectRatio="xMidYMid meet"
                         style="display:none;position:absolute;top:50%;left:50%;height:100%;aspect-ratio:3/4;transform:translate(-50%,-50%);pointer-events:none;z-index:3;">
                        <ellipse cx="150" cy="135" rx="56" ry="72" fill="none" stroke="#22c55e" stroke-width="2.5" opacity="0.85" stroke-dasharray="6 4"/>
                        <path d="M118 224 Q72 238 38 268 Q18 290 8 330 L0 400" fill="none" stroke="#22c55e" stroke-width="2" opacity="0.6" stroke-dasharray="6 4"/>
                        <path d="M182 224 Q228 238 262 268 Q282 290 292 330 L300 400" fill="none" stroke="#22c55e" stroke-width="2" opacity="0.6" stroke-dasharray="6 4"/>
                    </svg>
                </div>
                {{-- Sifat talablari paneli --}}
                <div id="photo-standards-panel" style="margin-top:12px;padding:10px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;text-align:left;">
                    <div style="font-size:12px;font-weight:700;color:#15803d;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Standartlar
                    </div>
                    <ul style="font-size:11.5px;color:#166534;line-height:1.55;margin:0;padding-left:18px;">
                        <li>Bosh markazda, yelkalargacha ko'rinib tursin (yashil silueta)</li>
                        <li>Yuz kadr balandligining kamida 35% ini egallashi shart</li>
                        <li>Oq tibbiy xalat va oq fon</li>
                        <li>Yorug'lik yuzga teng tushsin (ko'lankalar yo'q)</li>
                        <li>Ko'zoynak/niqob/qalpoqcha bo'lmasin, ifodaning betarafligi</li>
                    </ul>
                </div>
                <div id="modal-rejection-banner" style="display:none;margin-top:10px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;text-align:left;">
                    <div style="font-size:12px;font-weight:700;color:#dc2626;margin-bottom:3px;">Rad etildi</div>
                    <div id="modal-rejection-reason" style="font-size:12px;color:#991b1b;"></div>
                </div>
            </div>
            <div style="padding:0 20px 20px;">
                    <div id="photo-delete-wrap" style="display:none;margin-bottom:8px;">
                        <button type="button" onclick="deletePhoto()"
                                style="width:100%;padding:10px;background:#fee2e2;color:#991b1b;font-size:13px;font-weight:600;border:1px solid #fecaca;border-radius:10px;cursor:pointer;">
                            O'chirish
                        </button>
                    </div>
                    {{-- Rasm yuklash (fayldan) --}}
                    <input type="file" id="photo-file-input" accept="image/*" style="display:none;" onchange="handleFileUpload(this)">
                    <button type="button" id="photo-file-btn" onclick="document.getElementById('photo-file-input').click()"
                            style="width:100%;padding:12px;margin-bottom:8px;background:#f1f5f9;color:#334155;font-size:14px;font-weight:600;border:1px solid #cbd5e1;border-radius:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                        <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        Rasm yuklash
                    </button>
                    {{-- Kamera ochish --}}
                    <button type="button" id="photo-capture-btn" onclick="startCamera()"
                            style="width:100%;padding:12px;background:linear-gradient(135deg,#2b5ea7,#3b82f6);color:#fff;font-size:14px;font-weight:600;border:none;border-radius:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                        <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                        <span id="photo-btn-text">Rasmga olish</span>
                    </button>
                    {{-- Saqlash --}}
                    <button type="button" id="photo-save-btn" onclick="uploadPhoto()" style="display:none;width:100%;padding:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-size:14px;font-weight:600;border:none;border-radius:12px;cursor:pointer;">
                        Saqlash
                    </button>
                    {{-- Qayta tushirish --}}
                    <button type="button" id="photo-retake-btn" onclick="startCamera()" style="display:none;width:100%;padding:10px;margin-top:6px;background:#f1f5f9;color:#475569;font-size:13px;font-weight:600;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;">
                        Qayta tushirish
                    </button>
                    <div id="photo-progress" style="display:none;margin-top:8px;text-align:center;font-size:12px;color:#64748b;">Yuklanmoqda...</div>
            </div>
        </div>
    </div>
    @endif

    <script>
        var MAX_SIZE = 1200;
        var currentStudentId = null;
        var currentBlob = null;
        var uploadActionUrl = '';
        var cameraStream = null;
        var isPhotoFilterView = {{ request('photo_filter') ? 'true' : 'false' }};

        function stopCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(function(t) { t.stop(); });
                cameraStream = null;
            }
            document.getElementById('camera-video').style.display = 'none';
        }

        function handleFileUpload(input) {
            if (!input.files || !input.files[0]) return;
            var file = input.files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                var tempImg = new Image();
                tempImg.onload = function() {
                    var targetRatio = 3 / 4;
                    var srcW = tempImg.width, srcH = tempImg.height;
                    var srcRatio = srcW / srcH;
                    var cropW, cropH, cropX, cropY;
                    if (srcRatio > targetRatio) {
                        cropH = srcH;
                        cropW = Math.round(srcH * targetRatio);
                        cropX = Math.round((srcW - cropW) / 2);
                        cropY = 0;
                    } else {
                        cropW = srcW;
                        cropH = Math.round(srcW / targetRatio);
                        cropX = 0;
                        cropY = Math.round((srcH - cropH) / 3);
                    }
                    var outH = Math.min(cropH, MAX_SIZE);
                    var outW = Math.round(outH * targetRatio);
                    var canvas = document.createElement('canvas');
                    canvas.width = outW; canvas.height = outH;
                    canvas.getContext('2d').drawImage(tempImg, cropX, cropY, cropW, cropH, 0, 0, outW, outH);
                    canvas.toBlob(function(blob) {
                        currentBlob = blob;
                        var img = document.getElementById('modal-photo-img');
                        img.src = URL.createObjectURL(blob);
                        img.style.display = 'block';
                        document.getElementById('modal-no-photo').style.display = 'none';
                        document.getElementById('photo-save-btn').style.display = 'block';
                        document.getElementById('photo-retake-btn').style.display = 'block';
                        document.getElementById('photo-capture-btn').style.display = 'none';
                        document.getElementById('photo-file-btn').style.display = 'none';
                        document.getElementById('modal-photo-frame').style.borderStyle = 'solid';
                        document.getElementById('modal-photo-frame').style.borderColor = '#10b981';
                        document.getElementById('modal-photo-overlay').style.display = 'block';
                    }, 'image/jpeg', 0.92);
                };
                tempImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
            input.value = '';
        }

        function startCamera() {
            document.getElementById('camera-fullscreen').style.display = 'block';
            var video = document.getElementById('camera-video');

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1600 }, height: { ideal: 2100 } }, audio: false })
                .then(function(stream) {
                    cameraStream = stream;
                    video.srcObject = stream;
                })
                .catch(function(err) {
                    alert('Kamera ochilmadi: ' + err.message);
                    closeCameraFullscreen();
                });
        }

        function closeCameraFullscreen() {
            stopCamera();
            document.getElementById('camera-fullscreen').style.display = 'none';
        }

        function snapPhoto() {
            var video = document.getElementById('camera-video');
            var srcW = video.videoWidth, srcH = video.videoHeight;

            // 3:4 (portrait) ga qirqish
            var targetRatio = 3 / 4;
            var srcRatio = srcW / srcH;
            var cropW, cropH, cropX, cropY;
            if (srcRatio > targetRatio) {
                cropH = srcH;
                cropW = Math.round(srcH * targetRatio);
                cropX = Math.round((srcW - cropW) / 2);
                cropY = 0;
            } else {
                cropW = srcW;
                cropH = Math.round(srcW / targetRatio);
                cropX = 0;
                cropY = Math.round((srcH - cropH) / 3);
            }

            var outH = Math.min(cropH, MAX_SIZE);
            var outW = Math.round(outH * targetRatio);

            var canvas = document.createElement('canvas');
            canvas.width = outW; canvas.height = outH;
            canvas.getContext('2d').drawImage(video, cropX, cropY, cropW, cropH, 0, 0, outW, outH);

            closeCameraFullscreen();

            canvas.toBlob(function(blob) {
                currentBlob = blob;
                var img = document.getElementById('modal-photo-img');
                img.src = URL.createObjectURL(blob);
                img.style.display = 'block';
                document.getElementById('modal-no-photo').style.display = 'none';
                document.getElementById('photo-save-btn').style.display = 'block';
                document.getElementById('photo-retake-btn').style.display = 'block';
                document.getElementById('photo-capture-btn').style.display = 'none';
                document.getElementById('photo-file-btn').style.display = 'none';
                document.getElementById('modal-photo-frame').style.borderStyle = 'solid';
                document.getElementById('modal-photo-frame').style.borderColor = '#10b981';
                document.getElementById('modal-photo-overlay').style.display = 'block';
            }, 'image/jpeg', 0.92);
        }

        function deletePhoto() {
            if (!confirm('Rasmni o\'chirmoqchimisiz?')) return;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '/teacher/students/' + currentStudentId + '/delete-photo';
            form.innerHTML = '@csrf @method("DELETE")';
            document.body.appendChild(form);
            form.submit();
        }

        function openPhotoModal(studentId, name, idNumber, groupName, photoUrl, photoStatus, rejectionReason) {
            currentStudentId = studentId;
            currentBlob = null;
            uploadActionUrl = '/teacher/students/' + studentId + '/upload-photo';
            document.getElementById('modal-name').textContent = name;
            document.getElementById('modal-info').textContent = idNumber + ' · ' + groupName;

            var img = document.getElementById('modal-photo-img');
            var noPhoto = document.getElementById('modal-no-photo');
            var frame = document.getElementById('modal-photo-frame');
            var rejBanner = document.getElementById('modal-rejection-banner');

            document.getElementById('photo-retake-btn').style.display = 'none';

            if (photoStatus === 'rejected' && rejectionReason) {
                rejBanner.style.display = 'block';
                document.getElementById('modal-rejection-reason').textContent = 'Sabab: ' + rejectionReason;
            } else {
                rejBanner.style.display = 'none';
            }

            var overlay = document.getElementById('modal-photo-overlay');
            if (photoUrl) {
                img.src = photoUrl;
                img.style.display = 'block';
                noPhoto.style.display = 'none';
                frame.style.borderStyle = 'solid';
                frame.style.borderColor = '#3b82f6';
                if (overlay) overlay.style.display = 'block';
                document.getElementById('photo-btn-text').textContent = 'Qayta tushirish';
                document.getElementById('photo-capture-btn').style.display = isPhotoFilterView ? 'none' : 'flex';
                document.getElementById('photo-file-btn').style.display = isPhotoFilterView ? 'none' : 'flex';
                document.getElementById('photo-delete-wrap').style.display = isPhotoFilterView ? 'none' : 'block';
            } else {
                img.style.display = 'none';
                noPhoto.style.display = 'block';
                frame.style.borderStyle = 'dashed';
                frame.style.borderColor = '#cbd5e1';
                if (overlay) overlay.style.display = 'none';
                document.getElementById('photo-btn-text').textContent = 'Kamerani ochish';
                document.getElementById('photo-capture-btn').style.display = isPhotoFilterView ? 'none' : 'flex';
                document.getElementById('photo-file-btn').style.display = isPhotoFilterView ? 'none' : 'flex';
                document.getElementById('photo-delete-wrap').style.display = 'none';
            }
            document.getElementById('photo-save-btn').style.display = 'none';
            document.getElementById('photo-progress').style.display = 'none';

            document.getElementById('photo-modal').style.display = 'flex';
        }

        function closePhotoModal() {
            stopCamera();
            document.getElementById('photo-modal').style.display = 'none';
        }

        function uploadPhoto() {
            if (!currentBlob) return;
            var btn = document.getElementById('photo-save-btn');
            var prog = document.getElementById('photo-progress');
            btn.style.display = 'none';
            document.getElementById('photo-retake-btn').style.display = 'none';
            prog.style.display = 'block';
            prog.textContent = 'Yuklanmoqda...';

            var fd = new FormData();
            fd.append('photo', currentBlob, 'photo.jpg');
            fd.append('_token', '{{ csrf_token() }}');

            fetch(uploadActionUrl, { method: 'POST', body: fd })
                .then(function(r) { if (r.ok) { window.location.reload(); } else { throw new Error('Server xatosi'); } })
                .catch(function(err) { prog.textContent = 'Xatolik: ' + err.message; btn.style.display = 'block'; });
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
