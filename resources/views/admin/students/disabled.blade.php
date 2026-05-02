<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Nogiron talabalar') }}
        </h2>
    </x-slot>

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Xato!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Muvaffaqiyatli!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <form id="search-form" method="GET" action="{{ route('admin.students.disabled') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Nogironlik turi</label>
                                <select name="disability_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($disabilityTypes as $type)
                                        <option value="{{ $type->social_category_code }}" {{ request('disability_type') == $type->social_category_code ? 'selected' : '' }}>
                                            {{ $type->social_category_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                                <select name="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->department_id }}" {{ request('department') == $d->department_id ? 'selected' : '' }}>
                                            {{ $d->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 170px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <select name="group" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($groups as $g)
                                        <option value="{{ $g->group_id }}" {{ request('group') == $g->group_id ? 'selected' : '' }}>
                                            {{ $g->group_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 130px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select name="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($levels as $l)
                                        <option value="{{ $l->level_code }}" {{ request('level_code') == $l->level_code ? 'selected' : '' }}>
                                            {{ $l->level_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                                <select name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $ps)
                                        <option value="{{ $ps }}" {{ request('per_page', 50) == $ps ? 'selected' : '' }}>{{ $ps }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="filter-row">
                            <div class="filter-item" style="flex: 1; min-width: 220px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.Sh</label>
                                <input type="text" name="full_name" value="{{ request('full_name') }}"
                                       placeholder="Obidov Zohid" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ec4899;"></span> Talaba ID</label>
                                <input type="text" name="student_id_number" value="{{ request('student_id_number') }}"
                                       placeholder="1234" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#22c55e;"></span> Ma'lumot holati</label>
                                <select name="info_status" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    <option value="filled" {{ request('info_status') === 'filled' ? 'selected' : '' }}>To'ldirilgan</option>
                                    <option value="empty" {{ request('info_status') === 'empty' ? 'selected' : '' }}>To'ldirilmagan</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#dc2626;"></span> Talaba holati</label>
                                <select name="student_status" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    <option value="active" {{ request('student_status') === 'active' ? 'selected' : '' }}>Chetlashmagan</option>
                                    <option value="expelled" {{ request('student_status') === 'expelled' ? 'selected' : '' }}>Chetlashgan</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 120px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                            <div class="filter-item" style="min-width: 120px;">
                                <label class="filter-label">&nbsp;</label>
                                <a href="{{ route('admin.students.disabled') }}" class="btn-reset">
                                    Tozalash
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                @if(!($hasInfoTable ?? true))
                    <div style="padding:12px 20px;background:#fef3c7;border-bottom:1px solid #fde68a;color:#92400e;font-size:13px;">
                        <strong>Diqqat:</strong> <code>student_disability_infos</code> jadvali hali yaratilmagan. Server konsolida <code>php artisan migrate</code> ishga tushiring.
                    </div>
                @endif
                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <span class="badge" style="background:linear-gradient(135deg,#b91c1c,#ef4444);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ $totalAll }} ta nogiron talaba</span>
                        @if($hasInfoTable ?? true)
                            <span class="badge" style="background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">To'ldirgan: {{ $totalFilled }}</span>
                            <span class="badge" style="background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">To'ldirmagan: {{ $totalEmpty }}</span>
                        @endif
                        @if(($totalExpelled ?? 0) > 0)
                            <span class="badge" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Chetlashgan: {{ $totalExpelled }}</span>
                        @endif
                    </div>
                    <span style="font-size:12px;color:#64748b;">Sahifada: {{ $students->total() }} ta natija</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                        <tr>
                            <th style="width:50px;text-align:center;">#</th>
                            <th>F.I.Sh</th>
                            <th>Talaba ID</th>
                            <th>Guruh</th>
                            <th>Nogironlik turi</th>
                            <th>Talaba holati</th>
                            <th>Ko'rikdan o'tgan</th>
                            <th>Guruhi</th>
                            <th>Sababi</th>
                            <th>Muddati</th>
                            <th>Qayta ko'rik</th>
                            <th style="text-align:center;">Holat</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($students as $i => $student)
                            @php $info = ($hasInfoTable ?? true) ? $student->disabilityInfo : null; @endphp
                            <tr class="row-clickable" data-student-id="{{ $student->id }}" style="cursor:pointer;">
                                <td style="text-align:center;color:#64748b;">{{ $students->firstItem() + $i }}</td>
                                <td>
                                    <span class="student-name-link">{{ $student->full_name }}</span>
                                    <div style="font-size:11px;color:#94a3b8;">{{ $student->department_name }}</div>
                                </td>
                                <td style="color:#64748b;">{{ $student->student_id_number }}</td>
                                <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                <td><span class="badge badge-red" title="{{ $student->social_category_name }}">{{ Str::limit($student->social_category_name, 28) ?: '-' }}</span></td>
                                <td>
                                    @php $isExpelled = $student->student_status_name && stripos($student->student_status_name, 'chetlash') !== false; @endphp
                                    @if($isExpelled)
                                        <span class="badge badge-expelled" title="{{ $student->student_status_name }}">Chetlashgan</span>
                                    @else
                                        <span class="badge badge-active" title="{{ $student->student_status_name }}">{{ Str::limit($student->student_status_name, 18) ?: '—' }}</span>
                                    @endif
                                </td>
                                <td>{{ $info?->examined_at?->format('d.m.Y') ?: '—' }}</td>
                                <td>
                                    @if($info?->disability_group)
                                        <span class="badge badge-violet">{{ \App\Models\StudentDisabilityInfo::GROUPS[$info->disability_group] ?? $info->disability_group }}</span>
                                    @else — @endif
                                </td>
                                <td>
                                    @if($info?->disability_reason)
                                        <span class="text-cell" title="{{ $info->disability_reason }}">{{ Str::limit($info->disability_reason, 30) }}</span>
                                    @else — @endif
                                </td>
                                <td>{{ $info?->disability_duration ?: '—' }}</td>
                                <td>{{ $info?->reexamination_at?->format('d.m.Y') ?: '—' }}</td>
                                <td style="text-align:center;">
                                    @if($info)
                                        <span class="badge badge-green">To'ldirilgan</span>
                                    @else
                                        <span class="badge badge-amber">Kiritilmagan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" style="text-align:center;padding:40px 20px;color:#94a3b8;font-size:13px;">
                                    Nogiron talabalar topilmadi.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                    <div class="flex-1 flex justify-between sm:hidden">
                        {{ $students->appends(request()->query())->links('pagination::simple-tailwind') }}
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 leading-5">
                                {!! __('Showing') !!}
                                <span class="font-medium">{{ $students->firstItem() ?? 0 }}</span>
                                {!! __('to') !!}
                                <span class="font-medium">{{ $students->lastItem() ?? 0 }}</span>
                                {!! __('of') !!}
                                <span class="font-medium">{{ $students->total() }}</span>
                                {!! __('results') !!}
                            </p>
                        </div>
                        <div>
                            {{ $students->appends(request()->query())->links('pagination::tailwind') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Talaba ma'lumotlari modali --}}
    <div id="infoModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:20px;overflow-y:auto;">
        <div style="background:#fff;border-radius:14px;max-width:640px;width:100%;margin:auto;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="padding:18px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#fef2f2,#fef3c7);">
                <div>
                    <h3 id="infoModalTitle" style="font-size:16px;font-weight:700;color:#1e293b;margin:0;">Nogiron talaba ma'lumotlari</h3>
                    <p id="infoModalSubtitle" style="font-size:12px;color:#64748b;margin:2px 0 0;"></p>
                </div>
                <button type="button" onclick="closeInfoModal()" style="background:none;border:none;font-size:24px;color:#64748b;cursor:pointer;line-height:1;padding:0 6px;">&times;</button>
            </div>
            <div id="infoModalBody" style="padding:20px 22px;font-size:13px;color:#334155;">
                <div style="text-align:center;padding:40px 0;color:#94a3b8;">Yuklanmoqda...</div>
            </div>
            <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" onclick="closeInfoModal()" style="padding:7px 16px;font-size:12px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:#475569;cursor:pointer;">Yopish</button>
                <a id="infoModalProfile" href="#" style="padding:7px 16px;font-size:12px;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;border:none;border-radius:8px;text-decoration:none;font-weight:600;">Talaba profili</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text()
                });
            });

            $(document).on('click', '.row-clickable', function(e) {
                if ($(e.target).is('a, button, input, select')) return;
                openInfoModal($(this).data('student-id'));
            });

            $('#infoModal').on('click', function(e) {
                if (e.target === this) closeInfoModal();
            });
        });

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"']/g, function(c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }

        function openInfoModal(studentId) {
            var modal = document.getElementById('infoModal');
            modal.style.display = 'flex';
            document.getElementById('infoModalBody').innerHTML = '<div style="text-align:center;padding:40px 0;color:#94a3b8;">Yuklanmoqda...</div>';

            $.getJSON('{{ url('admin/students/disabled') }}/' + studentId + '/info')
                .done(function(d) {
                    var s = d.student || {};
                    var info = d.info;
                    document.getElementById('infoModalTitle').textContent = s.full_name || '—';
                    document.getElementById('infoModalSubtitle').textContent = (s.student_id_number || '') + ' • ' + (s.group_name || '') + ' • ' + (s.department_name || '');
                    document.getElementById('infoModalProfile').href = '{{ url('admin/students') }}/' + s.id;

                    var html = '';
                    html += '<div style="display:grid;grid-template-columns:160px 1fr;gap:8px 14px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #e2e8f0;">';
                    html += '<div style="color:#64748b;">Yo\'nalish:</div><div>' + escapeHtml(s.specialty_name || '—') + '</div>';
                    html += '<div style="color:#64748b;">Kurs:</div><div>' + escapeHtml(s.level_name || '—') + '</div>';
                    html += '<div style="color:#64748b;">Ijtimoiy toifa:</div><div><span style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:600;">' + escapeHtml(s.social_category_name || '—') + '</span></div>';
                    html += '</div>';

                    if (info) {
                        html += '<h4 style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 10px;">Nogironlik ma\'lumotlari</h4>';
                        html += '<div style="display:grid;grid-template-columns:200px 1fr;gap:8px 14px;">';
                        html += '<div style="color:#64748b;">Ko\'rikdan o\'tgan sana:</div><div style="font-weight:600;">' + escapeHtml(info.examined_at || '—') + '</div>';
                        html += '<div style="color:#64748b;">Nogironlik guruhi:</div><div><span style="background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:600;">' + escapeHtml(info.disability_group || '—') + '</span></div>';
                        html += '<div style="color:#64748b;">Nogironlik sababi:</div><div>' + escapeHtml(info.disability_reason || '—') + '</div>';
                        html += '<div style="color:#64748b;">Nogironlik muddati:</div><div style="font-weight:600;">' + escapeHtml(info.disability_duration || '—') + '</div>';
                        html += '<div style="color:#64748b;">Qayta ko\'rik sanasi:</div><div style="font-weight:600;">' + escapeHtml(info.reexamination_at || '—') + '</div>';
                        html += '<div style="color:#64748b;">Oxirgi yangilanish:</div><div style="color:#94a3b8;font-size:12px;">' + escapeHtml(info.updated_at || '—') + '</div>';
                        html += '</div>';

                        if (d.certificate_url) {
                            html += '<div style="margin-top:16px;padding:12px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;display:flex;align-items:center;justify-content:space-between;gap:10px;">';
                            html += '<div style="display:flex;align-items:center;gap:8px;"><svg style="width:18px;height:18px;color:#16a34a;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
                            html += '<span style="font-size:13px;font-weight:600;color:#166534;">Nogironlik malumotnomasi</span></div>';
                            html += '<a href="' + d.certificate_url + '" target="_blank" style="padding:5px 12px;font-size:11.5px;background:#16a34a;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">PDF ochish</a>';
                            html += '</div>';
                        } else {
                            html += '<div style="margin-top:16px;padding:12px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:12.5px;color:#92400e;">Malumotnoma PDF yuklanmagan.</div>';
                        }
                    } else {
                        html += '<div style="padding:24px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;text-align:center;font-size:13px;color:#92400e;">Talaba hali nogironlik ma\'lumotlarini to\'ldirmagan.</div>';
                    }

                    document.getElementById('infoModalBody').innerHTML = html;
                })
                .fail(function(xhr) {
                    document.getElementById('infoModalBody').innerHTML = '<div style="padding:24px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;text-align:center;font-size:13px;color:#991b1b;">Ma\'lumotni yuklashda xatolik (' + xhr.status + ').</div>';
                });
        }

        function closeInfoModal() {
            document.getElementById('infoModal').style.display = 'none';
        }
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .filter-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; box-sizing: border-box; }
        .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.2); }
        .filter-input::placeholder { color: #94a3b8; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .btn-reset { display: inline-flex; align-items: center; justify-content: center; padding: 8px 20px; background: #fff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; height: 36px; text-decoration: none; }
        .btn-reset:hover { background: #f1f5f9; border-color: #94a3b8; }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .student-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .student-table thead { position: sticky; top: 0; z-index: 10; }
        .student-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .student-table th { padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .student-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .student-table tbody tr:nth-child(even) { background: #f8fafc; }
        .student-table tbody tr:nth-child(odd) { background: #fff; }
        .student-table tbody tr:hover { background: #fef2f2 !important; box-shadow: inset 4px 0 0 #ef4444; }
        .row-clickable:hover .student-name-link { text-decoration: underline; }
        .student-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }

        .student-name-link { color: #1e40af; font-weight: 700; text-decoration: none; transition: all 0.15s; }
        .student-name-link:hover { color: #2b5ea7; text-decoration: underline; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
        .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; white-space: nowrap; }
        .badge-green { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; white-space: nowrap; }
        .badge-amber { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; white-space: nowrap; }
        .badge-expelled { background: linear-gradient(135deg,#7f1d1d,#dc2626); color: #fff; border: none; white-space: nowrap; }
        .badge-active { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; white-space: nowrap; }
    </style>
</x-app-layout>
