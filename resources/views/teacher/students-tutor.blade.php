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
            .photo-modal-box { max-width: 400px !important; }
            #modal-photo-frame { min-height: 300px !important; max-height: 60vh !important; }
            #modal-photo-img { max-height: 60vh !important; }
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
                                 onclick="openPhotoModal({{ $student->id }}, {{ json_encode($student->full_name) }}, {{ json_encode($student->student_id_number) }}, {{ json_encode($student->group_name) }}, {{ json_encode($studentPhoto ? asset($studentPhoto->photo_path) : '') }})"
                                 style="cursor:pointer;">
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
                                <div class="student-right">
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

    {{-- Fullscreen Camera --}}
    <div id="camera-fullscreen" style="display:none;position:fixed;inset:0;z-index:99999;background:#000;">
        <video id="camera-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
        <svg viewBox="0 0 300 400" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;">
            <ellipse cx="150" cy="130" rx="52" ry="65" fill="none" stroke="#f59e0b" stroke-width="2.5" opacity="0.9"/>
            <path d="M130 194 L130 210 Q130 220 118 225" fill="none" stroke="#f59e0b" stroke-width="2" opacity="0.7"/>
            <path d="M170 194 L170 210 Q170 220 182 225" fill="none" stroke="#f59e0b" stroke-width="2" opacity="0.7"/>
            <path d="M118 225 Q80 240 45 270 Q25 290 15 330 L10 400" fill="none" stroke="#f59e0b" stroke-width="2" opacity="0.5"/>
            <path d="M182 225 Q220 240 255 270 Q275 290 285 330 L290 400" fill="none" stroke="#f59e0b" stroke-width="2" opacity="0.5"/>
            <text x="150" y="388" text-anchor="middle" font-size="12" fill="#fbbf24" font-weight="600">Bosh va yelkalarni moslang</text>
        </svg>
        <div style="position:absolute;bottom:0;left:0;right:0;padding:20px;display:flex;align-items:center;justify-content:center;gap:16px;background:linear-gradient(transparent,rgba(0,0,0,0.7));">
            <button type="button" onclick="closeCameraFullscreen()" style="width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,0.2);border:2px solid rgba(255,255,255,0.5);color:#fff;font-size:20px;cursor:pointer;backdrop-filter:blur(4px);">&times;</button>
            <button type="button" onclick="snapPhoto()" style="width:72px;height:72px;border-radius:50%;background:#fff;border:4px solid rgba(255,255,255,0.5);cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#059669,#10b981);margin:auto;"></div>
            </button>
            <div style="width:50px;"></div>
        </div>
    </div>

    {{-- Photo Modal --}}
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
                    {{-- Placeholder --}}
                    <div id="modal-no-photo" style="color:#94a3b8;font-size:13px;z-index:1;">
                        <svg style="width:48px;height:48px;margin:0 auto 8px;color:#475569;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                        Rasm yuklanmagan
                    </div>
                    {{-- Tushirilgan rasm --}}
                    <img id="modal-photo-img" style="max-width:100%;max-height:70vh;width:auto;height:auto;object-fit:contain;display:none;z-index:1;" alt="">
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

    <script>
        var MAX_SIZE = 1200;
        var currentStudentId = null;
        var currentBlob = null;
        var uploadActionUrl = '';
        var cameraStream = null;

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

        function openPhotoModal(studentId, name, idNumber, groupName, photoUrl) {
            currentStudentId = studentId;
            currentBlob = null;
            uploadActionUrl = '/teacher/students/' + studentId + '/upload-photo';
            document.getElementById('modal-name').textContent = name;
            document.getElementById('modal-info').textContent = idNumber + ' · ' + groupName;

            var img = document.getElementById('modal-photo-img');
            var noPhoto = document.getElementById('modal-no-photo');
            var frame = document.getElementById('modal-photo-frame');

            document.getElementById('photo-retake-btn').style.display = 'none';

            if (photoUrl) {
                img.src = photoUrl;
                img.style.display = 'block';
                noPhoto.style.display = 'none';
                frame.style.borderStyle = 'solid';
                frame.style.borderColor = '#3b82f6';
                document.getElementById('photo-btn-text').textContent = 'Qayta tushirish';
                document.getElementById('photo-capture-btn').style.display = 'flex';
                document.getElementById('photo-file-btn').style.display = 'flex';
                document.getElementById('photo-delete-wrap').style.display = 'block';
            } else {
                img.style.display = 'none';
                noPhoto.style.display = 'block';
                frame.style.borderStyle = 'dashed';
                frame.style.borderColor = '#cbd5e1';
                document.getElementById('photo-btn-text').textContent = 'Kamerani ochish';
                document.getElementById('photo-capture-btn').style.display = 'flex';
                document.getElementById('photo-file-btn').style.display = 'flex';
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
