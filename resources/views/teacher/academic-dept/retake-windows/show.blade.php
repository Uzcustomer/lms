<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-windows.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Oynalar") }}
            </a>
            @if($window->session)
                <span class="text-gray-300">/</span>
                <a href="{{ route('admin.retake-sessions.show', $window->session->id) }}" class="text-sm text-blue-600 hover:underline">
                    {{ $window->session->name }}
                </a>
            @endif
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $window->specialty_name }} · {{ $window->level_name ?? $window->level_code }} · {{ $window->semester_name }}
            </h2>
            @php
                $statusLabels = ['active' => __("Ariza qabul ochiq"), 'study' => __("O'qish davri"), 'closed' => __("Tugagan")];
                $statusColors = ['active' => 'bg-green-100 text-green-800', 'study' => 'bg-blue-100 text-blue-800', 'closed' => 'bg-gray-200 text-gray-700'];
            @endphp
            <span class="px-2 py-0.5 text-[11px] font-medium rounded-full {{ $statusColors[$window->status] }}">
                {{ $statusLabels[$window->status] }}
            </span>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">

        {{-- Oyna haqida ma'lumot --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Yo'nalish") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ $window->specialty_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Kurs") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ $window->level_name ?? $window->level_code }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Semestr") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ $window->semester_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Sanalar") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">
                        {{ $window->start_date->format('Y-m-d') }} → {{ $window->end_date->format('Y-m-d') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Statistika --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Talabalar") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['students'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Arizalar") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['applications'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Kutilmoqda") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['pending'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Tasdiqlangan") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['approved'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <p class="text-xs text-gray-500 uppercase">{{ __("Rad etilgan") }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['rejected'] }}</p>
            </div>
        </div>

        {{-- Tasdiqlangan + guruhga biriktirilgan --}}
        @if(count($byRetakeGroup) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-green-50">
                    <h3 class="text-sm font-semibold text-green-900">
                        ✓ {{ __("Guruhlarga biriktirilgan tasdiqlangan arizalar") }}
                    </h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($byRetakeGroup as $bundle)
                        @php $rg = $bundle['group']; @endphp
                        <div class="p-4">
                            <div class="flex items-start justify-between flex-wrap gap-2 mb-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $rg?->name ?? '— guruh nomi yo\'q —' }}</p>
                                    <p class="text-xs text-gray-600 mt-0.5">
                                        {{ __("Fan") }}: <span class="font-medium">{{ $bundle['applications']->first()->subject_name }}</span> ·
                                        {{ __("O'qituvchi") }}: <span class="font-medium">{{ $rg?->teacher_name ?? $rg?->teacher?->full_name ?? '—' }}</span>
                                        @if($rg?->start_date)
                                            · {{ $rg->start_date?->format('Y-m-d') }} → {{ $rg->end_date?->format('Y-m-d') }}
                                        @endif
                                    </p>
                                </div>
                                <span class="px-2 py-0.5 text-[11px] font-medium bg-green-100 text-green-800 rounded-full">
                                    {{ count($bundle['applications']) }} {{ __("ta talaba") }}
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-500">
                                        <tr>
                                            <th class="px-2 py-1.5 text-left">F.I.Sh</th>
                                            <th class="px-2 py-1.5 text-left">HEMIS ID</th>
                                            <th class="px-2 py-1.5 text-left">{{ __("Fakultet") }}</th>
                                            <th class="px-2 py-1.5 text-left">{{ __("Yo'nalish") }}</th>
                                            <th class="px-2 py-1.5 text-left">{{ __("Guruh") }}</th>
                                            <th class="px-2 py-1.5 text-right">{{ __("Kredit") }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($bundle['applications'] as $a)
                                            @php $st = $a->group->student; @endphp
                                            <tr>
                                                <td class="px-2 py-1.5 text-gray-900 font-medium">{{ $st?->full_name ?? '—' }}</td>
                                                <td class="px-2 py-1.5 text-gray-600">{{ $a->student_hemis_id }}</td>
                                                <td class="px-2 py-1.5 text-gray-700">{{ $st?->department_name ?? '—' }}</td>
                                                <td class="px-2 py-1.5 text-gray-700">{{ $st?->specialty_name ?? '—' }}</td>
                                                <td class="px-2 py-1.5 text-gray-700">{{ $st?->group_name ?? '—' }}</td>
                                                <td class="px-2 py-1.5 text-gray-700 text-right">{{ number_format($a->credit, 1) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tasdiqlangan, ammo guruh biriktirilmagan --}}
        @if(count($approvedNoGroup) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-yellow-50">
                    <h3 class="text-sm font-semibold text-yellow-900">
                        ⏳ {{ __("Tasdiqlangan, lekin guruhga biriktirilmagan") }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">F.I.Sh</th>
                                <th class="px-3 py-2 text-left">HEMIS ID</th>
                                <th class="px-3 py-2 text-left">{{ __("Fan") }}</th>
                                <th class="px-3 py-2 text-left">{{ __("Guruh") }}</th>
                                <th class="px-3 py-2 text-right">{{ __("Kredit") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($approvedNoGroup as $a)
                                @php $st = $a->group->student; @endphp
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 font-medium">{{ $st?->full_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $a->student_hemis_id }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $a->subject_name }} ({{ $a->semester_name }})</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $st?->group_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700 text-right">{{ number_format($a->credit, 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Rad etilgan arizalar --}}
        @if(count($rejected) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-red-50">
                    <h3 class="text-sm font-semibold text-red-900">
                        ✗ {{ __("Rad etilgan arizalar") }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">F.I.Sh</th>
                                <th class="px-3 py-2 text-left">HEMIS ID</th>
                                <th class="px-3 py-2 text-left">{{ __("Fan") }}</th>
                                <th class="px-3 py-2 text-left">{{ __("Kim rad etgan") }}</th>
                                <th class="px-3 py-2 text-left">{{ __("Sabab") }}</th>
                                <th class="px-3 py-2 text-left">{{ __("Sana") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($rejected as $a)
                                @php
                                    $st = $a->group->student;
                                    $rejByLabel = match($a->rejected_by) {
                                        'dean' => __('Dekan'),
                                        'registrar' => __('Registrator'),
                                        'academic_dept' => __("O'quv bo'limi"),
                                        'system_hemis' => __("Tizim (HEMIS)"),
                                        'window_closed' => __("Oyna yopildi"),
                                        default => __('Tizim'),
                                    };
                                    $rejUserName = match($a->rejected_by) {
                                        'dean' => $a->dean_user_name,
                                        'registrar' => $a->registrar_user_name,
                                        'academic_dept' => $a->academic_dept_user_name,
                                        default => null,
                                    };
                                    $rejReason = $a->rejectionReason();
                                    $rejAt = match($a->rejected_by) {
                                        'dean' => $a->dean_decision_at,
                                        'registrar' => $a->registrar_decision_at,
                                        'academic_dept' => $a->academic_dept_decision_at,
                                        default => null,
                                    };
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 font-medium">{{ $st?->full_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $a->student_hemis_id }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $a->subject_name }} ({{ $a->semester_name }})</td>
                                    <td class="px-3 py-2 text-gray-700">
                                        <span class="font-medium text-red-700">{{ $rejByLabel }}</span>
                                        @if($rejUserName)
                                            <br><span class="text-gray-500 text-[11px]">{{ $rejUserName }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $rejReason ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500 text-[11px]">
                                        {{ $rejAt ? \Carbon\Carbon::parse($rejAt)->format('Y-m-d H:i') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Pending — odatda yopilgan oynada bo'lmasligi kerak (auto-reject) --}}
        @if(count($pending) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-yellow-50">
                    <h3 class="text-sm font-semibold text-yellow-900">
                        ⏳ {{ __("Hali ko'rib chiqilmagan arizalar") }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">F.I.Sh</th>
                                <th class="px-3 py-2 text-left">HEMIS ID</th>
                                <th class="px-3 py-2 text-left">{{ __("Fan") }}</th>
                                <th class="px-3 py-2 text-left">{{ __("Bosqich") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($pending as $a)
                                @php $st = $a->group->student; @endphp
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 font-medium">{{ $st?->full_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $a->student_hemis_id }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $a->subject_name }} ({{ $a->semester_name }})</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $a->studentDisplayStatus() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($stats['applications'] === 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                <p class="text-gray-500 text-sm">{{ __("Bu oynada hech qanday ariza yuborilmagan") }}</p>
            </div>
        @endif

    </div>
</x-teacher-app-layout>
