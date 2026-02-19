<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Yakuniy nazoratlar jadvali
            </h2>
        </div>
    </x-slot>

    <div x-data="testCenterSchedule()" class="space-y-6">
        {{-- Filtrlar --}}
        <div class="p-6 bg-white rounded-lg shadow">
            <h3 class="mb-4 text-lg font-medium text-gray-900">Filtrlar</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                {{-- Fakultet --}}
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Fakultet</label>
                    <select x-model="departmentId" @change="onDepartmentChange()"
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">-- Tanlang --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->department_hemis_id }}"
                                {{ $selectedDepartment == $dept->department_hemis_id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Yo'nalish --}}
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Yo'nalish</label>
                    <select x-model="specialtyId" @change="onSpecialtyChange()"
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">-- Barchasi --</option>
                        <template x-for="sp in specialties" :key="sp.specialty_hemis_id">
                            <option :value="sp.specialty_hemis_id" x-text="sp.name"
                                :selected="sp.specialty_hemis_id == '{{ $selectedSpecialty }}'"></option>
                        </template>
                    </select>
                </div>

                {{-- Semestr --}}
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Semestr</label>
                    <select x-model="semesterCode"
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">-- Barchasi --</option>
                        <template x-for="sem in semesters" :key="sem.code">
                            <option :value="sem.code" x-text="sem.name"
                                :selected="sem.code == '{{ $selectedSemester }}'"></option>
                        </template>
                    </select>
                </div>

                {{-- Guruh --}}
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Guruh</label>
                    <select x-model="groupId"
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">-- Barchasi --</option>
                        <template x-for="gr in groups" :key="gr.group_hemis_id">
                            <option :value="gr.group_hemis_id" x-text="gr.name"
                                :selected="gr.group_hemis_id == '{{ $selectedGroup }}'"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button @click="applyFilter()"
                    class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Qidirish
                </button>
            </div>
        </div>

        {{-- Natijalar --}}
        @if($schedules->count() > 0)
        <div class="overflow-hidden bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">
                    Imtihon jadvali
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Quyida o'quv bo'limi tomonidan belgilangan OSKI va Test imtihon sanalari ko'rsatilgan.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                #
                            </th>
                            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                Guruh
                            </th>
                            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                Fan nomi
                            </th>
                            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                Semestr
                            </th>
                            <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                OSKI sanasi
                            </th>
                            <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                Test sanasi
                            </th>
                            <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                Holat
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php $rowIndex = 0; @endphp
                        @foreach($schedules as $groupHemisId => $items)
                            {{-- Guruh sarlavhasi --}}
                            <tr class="bg-indigo-50">
                                <td colspan="7" class="px-4 py-2 text-sm font-semibold text-indigo-800">
                                    @php
                                        $firstItem = $items->first();
                                        $groupModel = $firstItem->group;
                                    @endphp
                                    {{ $groupModel?->name ?? $groupHemisId }}
                                    @if($groupModel?->specialty_name)
                                        <span class="ml-2 text-xs font-normal text-indigo-600">
                                            ({{ $groupModel->specialty_name }})
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @foreach($items as $schedule)
                                @php
                                    $now = now();
                                    $oskiPassed = $schedule->oski_date && $schedule->oski_date->lt($now);
                                    $testPassed = $schedule->test_date && $schedule->test_date->lt($now);
                                    $oskiToday = $schedule->oski_date && $schedule->oski_date->isToday();
                                    $testToday = $schedule->test_date && $schedule->test_date->isToday();
                                    $oskiSoon = $schedule->oski_date && !$oskiPassed && $schedule->oski_date->diffInDays($now) <= 3;
                                    $testSoon = $schedule->test_date && !$testPassed && $schedule->test_date->diffInDays($now) <= 3;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ ++$rowIndex }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $schedule->group?->name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $schedule->subject_name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ $schedule->semester_code }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        @if($schedule->oski_date)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold leading-5 rounded-full
                                                {{ $oskiToday ? 'bg-yellow-100 text-yellow-800' : ($oskiPassed ? 'bg-gray-100 text-gray-600' : ($oskiSoon ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800')) }}">
                                                {{ $schedule->oski_date->format('d.m.Y') }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        @if($schedule->test_date)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold leading-5 rounded-full
                                                {{ $testToday ? 'bg-yellow-100 text-yellow-800' : ($testPassed ? 'bg-gray-100 text-gray-600' : ($testSoon ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800')) }}">
                                                {{ $schedule->test_date->format('d.m.Y') }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        @if(($schedule->oski_date && $oskiToday) || ($schedule->test_date && $testToday))
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold leading-5 text-yellow-800 bg-yellow-100 rounded-full">
                                                Bugun
                                            </span>
                                        @elseif(($schedule->oski_date && $oskiSoon) || ($schedule->test_date && $testSoon))
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold leading-5 text-orange-800 bg-orange-100 rounded-full">
                                                Yaqinda
                                            </span>
                                        @elseif(($schedule->oski_date && !$oskiPassed) || ($schedule->test_date && !$testPassed))
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold leading-5 text-green-800 bg-green-100 rounded-full">
                                                Kutilmoqda
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold leading-5 text-gray-600 bg-gray-100 rounded-full">
                                                O'tgan
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @elseif($selectedDepartment)
            <div class="p-6 text-center bg-white rounded-lg shadow">
                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Jadval topilmadi</h3>
                <p class="mt-1 text-sm text-gray-500">Tanlangan filtrlar bo'yicha imtihon sanalari hali belgilanmagan.</p>
            </div>
        @else
            <div class="p-6 text-center bg-white rounded-lg shadow">
                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Filtrlang</h3>
                <p class="mt-1 text-sm text-gray-500">Fakultetni tanlab, yakuniy nazoratlar jadvalini ko'ring.</p>
            </div>
        @endif
    </div>

    <script>
        function testCenterSchedule() {
            return {
                departmentId: '{{ $selectedDepartment }}',
                specialtyId: '{{ $selectedSpecialty }}',
                semesterCode: '{{ $selectedSemester }}',
                groupId: '{{ $selectedGroup }}',
                specialties: @json($specialties),
                semesters: @json($semesters),
                groups: @json($groups),

                onDepartmentChange() {
                    this.specialtyId = '';
                    this.semesterCode = '';
                    this.groupId = '';
                    this.specialties = [];
                    this.semesters = [];
                    this.groups = [];

                    if (!this.departmentId) return;

                    fetch(`{{ route($routePrefix . '.academic-schedule.get-specialties') }}?department_id=${this.departmentId}`)
                        .then(r => r.json())
                        .then(data => this.specialties = data);

                    fetch(`{{ route($routePrefix . '.academic-schedule.get-semesters') }}?department_id=${this.departmentId}`)
                        .then(r => r.json())
                        .then(data => this.semesters = data);

                    fetch(`{{ route($routePrefix . '.academic-schedule.get-groups') }}?department_id=${this.departmentId}`)
                        .then(r => r.json())
                        .then(data => this.groups = data);
                },

                onSpecialtyChange() {
                    this.semesterCode = '';
                    this.groupId = '';

                    let url = `{{ route($routePrefix . '.academic-schedule.get-semesters') }}?department_id=${this.departmentId}`;
                    if (this.specialtyId) url += `&specialty_id=${this.specialtyId}`;
                    fetch(url).then(r => r.json()).then(data => this.semesters = data);

                    let groupUrl = `{{ route($routePrefix . '.academic-schedule.get-groups') }}?department_id=${this.departmentId}`;
                    if (this.specialtyId) groupUrl += `&specialty_id=${this.specialtyId}`;
                    fetch(groupUrl).then(r => r.json()).then(data => this.groups = data);
                },

                applyFilter() {
                    let url = new URL(window.location.href.split('?')[0]);
                    if (this.departmentId) url.searchParams.set('department_id', this.departmentId);
                    if (this.specialtyId) url.searchParams.set('specialty_id', this.specialtyId);
                    if (this.semesterCode) url.searchParams.set('semester_code', this.semesterCode);
                    if (this.groupId) url.searchParams.set('group_id', this.groupId);
                    window.location.href = url.toString();
                }
            }
        }
    </script>
</x-app-layout>
