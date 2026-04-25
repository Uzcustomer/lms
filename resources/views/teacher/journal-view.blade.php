<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Jurnalni ko'rish</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="jv-header">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <h3 class="text-white font-semibold text-sm uppercase tracking-wide">Mening guruhlarim</h3>
                        <span class="ml-auto jv-badge">{{ count($groups ?? []) }} ta</span>
                    </div>
                </div>
                <div class="p-5">
                    @if(empty($groups) || count($groups) === 0)
                        <div class="py-8 text-center text-gray-400 text-sm">Sizga biriktirilgan guruhlar yo'q</div>
                    @else
                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">Guruhni tanlang</label>
                        <select id="group-select" class="jv-select">
                            @foreach($groups as $group)
                                <option value="{{ $group->group_hemis_id }}" {{ $loop->first ? 'selected' : '' }}>
                                    {{ $group->name }} &mdash; {{ $group->specialty_name ?? '—' }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            @if(!empty($groups) && count($groups) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="jv-header-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <h3 class="text-white font-semibold text-sm uppercase tracking-wide">Joriy semestr fanlari</h3>
                        </div>
                        <span id="subjects-count" class="jv-badge">0 ta</span>
                    </div>
                </div>

                <div class="p-5">
                    <div id="subjects-loading" style="display:none;" class="py-12 text-center">
                        <div class="inline-block w-8 h-8 border-3 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div>
                        <p class="text-gray-500 text-sm mt-3">Fanlar yuklanmoqda...</p>
                    </div>
                    <div id="subjects-list" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                    <div id="subjects-empty" style="display:none;" class="py-12 text-center text-gray-400 text-sm">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Bu guruh uchun joriy semestrda fanlar topilmadi
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>

    <style>
        .jv-header { padding: 14px 18px; background: linear-gradient(135deg, #1a3268, #2b5ea7); }
        .jv-header-2 { padding: 14px 18px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
        .jv-badge { background: rgba(255,255,255,0.2); color: #fff; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .jv-select {
            width: 100%; padding: 11px 14px; font-size: 14px; font-weight: 600; color: #1e293b;
            border: 2px solid #e2e8f0; border-radius: 10px; background: #fff; outline: none;
            transition: all 0.18s; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%232b5ea7'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; background-size: 18px;
            padding-right: 40px; -webkit-appearance: none; -moz-appearance: none; appearance: none;
        }
        .jv-select:hover { border-color: #2b5ea7; }
        .jv-select:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .subject-card {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 14px 16px; border-radius: 10px; cursor: pointer; text-decoration: none;
            border: 1px solid #e2e8f0; transition: all 0.18s; background: #fff;
        }
        .subject-card:hover { border-color: #2b5ea7; background: linear-gradient(135deg, #eff6ff, #fff); box-shadow: 0 4px 12px rgba(43,94,167,0.12); transform: translateY(-1px); }
        .subject-card .subject-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #dbeafe, #bfdbfe); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .subject-card:hover .subject-icon { background: linear-gradient(135deg, #1a3268, #2b5ea7); }
        .subject-card .subject-icon svg { color: #2b5ea7; transition: color 0.18s; }
        .subject-card:hover .subject-icon svg { color: #fff; }
        .subject-card .subject-info { flex: 1; min-width: 0; }
        .subject-card .subject-name { font-weight: 700; font-size: 13.5px; color: #0f172a; line-height: 1.3; }
        .subject-card .subject-meta { font-size: 11.5px; color: #64748b; margin-top: 3px; display: flex; align-items: center; gap: 8px; }
        .subject-card .subject-meta .pill { padding: 2px 8px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 999px; font-weight: 600; font-size: 10.5px; }
        .subject-card .arrow { color: #cbd5e1; font-size: 18px; flex-shrink: 0; transition: all 0.18s; }
        .subject-card:hover .arrow { color: #2b5ea7; transform: translateX(3px); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .subject-card { animation: fadeInUp 0.2s ease-out; }
        .border-3 { border-width: 3px; }
    </style>

    <script>
        var subjectsUrl = '{{ route("teacher.journal-view.subjects") }}';
        var journalShowUrl = '{{ url("admin/journal/show") }}';
        var currentGroupDbId = null;

        @if(!empty($groups) && count($groups) > 0)
            document.addEventListener('DOMContentLoaded', function() {
                var sel = document.getElementById('group-select');
                if (sel) {
                    loadSubjects(sel.value);
                    sel.addEventListener('change', function() { loadSubjects(this.value); });
                }
            });
        @endif

        function loadSubjects(groupHemisId) {
            document.getElementById('subjects-list').innerHTML = '';
            document.getElementById('subjects-empty').style.display = 'none';
            document.getElementById('subjects-loading').style.display = 'block';
            document.getElementById('subjects-count').textContent = '...';

            fetch(subjectsUrl + '?group_id=' + encodeURIComponent(groupHemisId))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('subjects-loading').style.display = 'none';
                    currentGroupDbId = data.group_db_id;
                    var subjects = data.subjects || [];
                    document.getElementById('subjects-count').textContent = subjects.length + ' ta';
                    if (subjects.length === 0) {
                        document.getElementById('subjects-empty').style.display = 'block';
                        return;
                    }
                    var html = '';
                    subjects.forEach(function(s) {
                        var url = journalShowUrl + '/' + currentGroupDbId + '/' + s.subject_id + '/' + s.semester_code;
                        html += '<a href="' + url + '" class="subject-card">';
                        html += '<div class="subject-icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></div>';
                        html += '<div class="subject-info">';
                        html += '<div class="subject-name">' + escHtml(s.subject_name) + '</div>';
                        html += '<div class="subject-meta"><span class="pill">' + escHtml(s.semester_name || s.semester_code) + '</span></div>';
                        html += '</div>';
                        html += '<div class="arrow">&rarr;</div>';
                        html += '</a>';
                    });
                    document.getElementById('subjects-list').innerHTML = html;
                })
                .catch(function() {
                    document.getElementById('subjects-loading').style.display = 'none';
                    document.getElementById('subjects-count').textContent = '0 ta';
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
