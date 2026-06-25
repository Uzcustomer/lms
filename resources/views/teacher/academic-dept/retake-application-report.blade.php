<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-statistics.index') }}" class="text-sm text-blue-600 hover:underline">← {{ __("Statistika") }}</a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __("Qayta o'qish arizasi hisoboti") }}</h2>
        </div>
    </x-slot>

    <style>
        .rar-table { width:100%; border-collapse:collapse; font-size:12px; }
        .rar-table th, .rar-table td { border:1px solid #cbd5e1; padding:5px 7px; text-align:center; }
        .rar-table thead th { background:#cfe0f3; color:#1e3a5f; font-weight:600; }
        .rar-table td.subj { text-align:left; }
        .rar-grp-a { background:#eef6ee; }
        .rar-grp-b { background:#eef2fb; }
        .rar-col-c { background:#eef6fb; }
        .rar-col-d { background:#fff7ed; }
        .rar-col-e { background:#fdeee0; }
        .rar-table tbody tr:hover td { background:#f1f5f9; }
        .rar-total td { background:#fde68a !important; font-weight:700; }
    </style>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-application-report.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'subjects' => $subjects ?? collect(),
            'hiddenFilters' => ['full_name', 'subject'],
        ])

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-4">
            <div id="rar-loading" class="p-10 text-center text-sm text-gray-500">{{ __("Yuklanmoqda...") }}</div>
            <div id="rar-empty" class="p-10 text-center text-sm text-gray-500" style="display:none;">{{ __("Ma'lumot topilmadi") }}</div>
            <div class="overflow-x-auto" id="rar-wrap" style="display:none;">
                <table class="rar-table">
                    <thead>
                        <tr>
                            <th rowspan="2">T/R</th>
                            <th rowspan="2">{{ __("Fan") }}</th>
                            <th rowspan="2">{{ __("Ariza semestri") }}</th>
                            <th colspan="4" class="rar-grp-a">{{ __("Ariza berib tasdiqlanib guruhga qo'yilganlar") }}</th>
                            <th colspan="4" class="rar-grp-b">{{ __("Joriy semestrdan qarzdorlar ariza berganlar") }}</th>
                            <th rowspan="2" class="rar-col-c">{{ __("Ariza berib, tasdiqlanish jarayonida bo'lganlar") }}</th>
                            <th rowspan="2" class="rar-col-d">{{ __("Qayta o'qishga ariza bermaganlar") }}</th>
                            <th rowspan="2" class="rar-col-e">{{ __("Joriy semestrdan ariza bermagan qarzdorlar") }}</th>
                        </tr>
                        <tr>
                            <th class="rar-grp-a">{{ __("O'tgan") }}</th>
                            <th class="rar-grp-a">{{ __("Yiqilgan") }}</th>
                            <th class="rar-grp-a">{{ __("Imtihon topshirmagan") }}</th>
                            <th class="rar-grp-a">{{ __("Jami") }}</th>
                            <th class="rar-grp-b">{{ __("O'tgan") }}</th>
                            <th class="rar-grp-b">{{ __("Yiqilgan") }}</th>
                            <th class="rar-grp-b">{{ __("Imtihon topshirmagan") }}</th>
                            <th class="rar-grp-b">{{ __("Jami") }}</th>
                        </tr>
                    </thead>
                    <tbody id="rar-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const dataUrl = '{{ route('admin.retake-application-report.data') }}' + window.location.search;
                fetch(dataUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(({ rows, totals }) => {
                        const loading = document.getElementById('rar-loading');
                        loading.style.display = 'none';
                        if (!rows || !rows.length) {
                            document.getElementById('rar-empty').style.display = '';
                            return;
                        }
                        const body = document.getElementById('rar-body');
                        const cell = v => `<td>${v || 0}</td>`;
                        rows.forEach(r => {
                            body.insertAdjacentHTML('beforeend',
                                `<tr>
                                    <td>${r.tr}</td>
                                    <td class="subj">${r.subject_name ?? ''}</td>
                                    <td>${r.semester_name ?? ''}</td>
                                    <td class="rar-grp-a">${r.approved.pass}</td>
                                    <td class="rar-grp-a">${r.approved.fail}</td>
                                    <td class="rar-grp-a">${r.approved.none}</td>
                                    <td class="rar-grp-a"><b>${r.approved.jami}</b></td>
                                    <td class="rar-grp-b">${r.current.pass}</td>
                                    <td class="rar-grp-b">${r.current.fail}</td>
                                    <td class="rar-grp-b">${r.current.none}</td>
                                    <td class="rar-grp-b"><b>${r.current.jami}</b></td>
                                    <td class="rar-col-c">${r.in_process}</td>
                                    <td class="rar-col-d">${r.not_applied}</td>
                                    <td class="rar-col-e">${r.current_not_applied}</td>
                                </tr>`);
                        });
                        body.insertAdjacentHTML('beforeend',
                            `<tr class="rar-total">
                                <td colspan="3">{{ __("JAMI") }}</td>
                                <td>${totals.approved.pass}</td><td>${totals.approved.fail}</td><td>${totals.approved.none}</td><td>${totals.approved.jami}</td>
                                <td>${totals.current.pass}</td><td>${totals.current.fail}</td><td>${totals.current.none}</td><td>${totals.current.jami}</td>
                                <td>${totals.in_process}</td><td>${totals.not_applied}</td><td>${totals.current_not_applied}</td>
                            </tr>`);
                        document.getElementById('rar-wrap').style.display = '';
                    })
                    .catch(() => {
                        document.getElementById('rar-loading').textContent = 'Xatolik yuz berdi';
                    });
            });
        </script>
    @endpush
</x-teacher-app-layout>
