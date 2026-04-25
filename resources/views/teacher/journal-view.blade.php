<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Jurnalni ko'rish</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div style="padding:16px 20px 12px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef;">
                    <p style="font-size:13px;color:#475569;font-weight:600;">Guruhni tanlang, keyin fanni bosib jurnalni ko'ring</p>
                </div>

                <div style="padding:20px;display:flex;gap:20px;flex-wrap:wrap;min-height:400px;">
                    {{-- Guruhlar --}}
                    <div style="min-width:220px;max-width:280px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#475569;letter-spacing:0.05em;margin-bottom:10px;">Guruhlar</div>
                        @forelse($groups as $group)
                            <div class="group-card {{ $loop->first ? 'active' : '' }}"
                                 data-group-hemis-id="{{ $group->group_hemis_id }}"
                                 onclick="selectGroup(this, '{{ $group->group_hemis_id }}')">
                                <div style="font-weight:700;font-size:14px;color:#1e293b;">{{ $group->name }}</div>
                                <div style="font-size:11px;color:#64748b;margin-top:2px;">{{ $group->specialty_name ?? '' }}</div>
                            </div>
                        @empty
                            <div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px;">Guruhlar topilmadi</div>
                        @endforelse
                    </div>

                    {{-- Fanlar --}}
                    <div style="flex:1;min-width:300px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#475569;letter-spacing:0.05em;margin-bottom:10px;">Fanlar (joriy semestr)</div>
                        <div id="subjects-loading" style="display:none;padding:40px;text-align:center;">
                            <div style="display:inline-block;width:24px;height:24px;border:3px solid #e2e8f0;border-top-color:#2b5ea7;border-radius:50%;animation:spin 0.6s linear infinite;"></div>
                            <p style="color:#64748b;font-size:13px;margin-top:8px;">Yuklanmoqda...</p>
                        </div>
                        <div id="subjects-list"></div>
                        <div id="subjects-empty" style="display:none;padding:40px;text-align:center;color:#94a3b8;font-size:13px;">Fanlar topilmadi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        .group-card {
            padding: 10px 14px; margin-bottom: 6px; border-radius: 8px; cursor: pointer;
            border: 1px solid #e2e8f0; transition: all 0.15s; background: #fff;
        }
        .group-card:hover { border-color: #2b5ea7; background: #f0f7ff; }
        .group-card.active { border-color: #2b5ea7; background: linear-gradient(135deg, #1a3268, #2b5ea7); }
        .group-card.active div { color: #fff !important; }
        .subject-card {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; margin-bottom: 6px; border-radius: 8px; cursor: pointer;
            border: 1px solid #e2e8f0; transition: all 0.15s; background: #fff;
        }
        .subject-card:hover { border-color: #2b5ea7; background: #f0f7ff; box-shadow: 0 2px 8px rgba(43,94,167,0.1); }
        .subject-card .subject-name { font-weight: 600; font-size: 13px; color: #1e293b; }
        .subject-card .subject-semester { font-size: 11px; color: #64748b; margin-top: 2px; }
        .subject-card .arrow { color: #94a3b8; font-size: 18px; }
        .subject-card:hover .arrow { color: #2b5ea7; }
    </style>

    <script>
        var subjectsUrl = '{{ route("teacher.journal-view.subjects") }}';
        var journalShowUrl = '{{ url("admin/journal/show") }}';
        var currentGroupDbId = null;

        @if($groups->isNotEmpty())
            document.addEventListener('DOMContentLoaded', function() {
                selectGroup(document.querySelector('.group-card'), '{{ $groups->first()->group_hemis_id }}');
            });
        @endif

        function selectGroup(el, groupHemisId) {
            document.querySelectorAll('.group-card').forEach(function(c) { c.classList.remove('active'); });
            el.classList.add('active');

            document.getElementById('subjects-list').innerHTML = '';
            document.getElementById('subjects-empty').style.display = 'none';
            document.getElementById('subjects-loading').style.display = 'block';

            fetch(subjectsUrl + '?group_id=' + encodeURIComponent(groupHemisId))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('subjects-loading').style.display = 'none';
                    currentGroupDbId = data.group_db_id;
                    var subjects = data.subjects || [];
                    if (subjects.length === 0) {
                        document.getElementById('subjects-empty').style.display = 'block';
                        return;
                    }
                    var html = '';
                    subjects.forEach(function(s) {
                        var url = journalShowUrl + '/' + currentGroupDbId + '/' + s.subject_id + '/' + s.semester_code;
                        html += '<a href="' + url + '" class="subject-card">';
                        html += '<div><div class="subject-name">' + escHtml(s.subject_name) + '</div>';
                        html += '<div class="subject-semester">' + escHtml(s.semester_name || s.semester_code) + '</div></div>';
                        html += '<div class="arrow">&rarr;</div>';
                        html += '</a>';
                    });
                    document.getElementById('subjects-list').innerHTML = html;
                })
                .catch(function() {
                    document.getElementById('subjects-loading').style.display = 'none';
                    document.getElementById('subjects-empty').style.display = 'block';
                });
        }

        function escHtml(text) {
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }
    </script>
</x-teacher-app-layout>
