<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                YN kunini belgilash
            </h2>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="relative px-4 py-3 mb-4 text-green-700 bg-green-100 border border-green-400 rounded" role="alert">
            <strong class="font-bold">Muvaffaqiyat!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="relative px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
            <strong class="font-bold">Xato!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <div x-data="academicSchedule()" class="space-y-6">
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
                    <select x-model="semesterCode" @change="onFilterChange()"
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">-- Tanlang --</option>
                        <template x-for="sem in semesters" :key="sem.code">
                            <option :value="sem.code" x-text="sem.name"
                                :selected="sem.code == '{{ $selectedSemester }}'"></option>
                        </template>
                    </select>
                </div>

                {{-- Guruh --}}
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Guruh</label>
                    <select x-model="groupId" @change="onFilterChange()"
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

        {{-- Natijalar jadvali --}}
        @if($scheduleData->count() > 0)
        <form method="POST" action="{{ route($routePrefix . '.academic-schedule.store') }}">
            @csrf
            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">
                        Imtihon sanalari
                        @if($currentEducationYear)
                            <span class="text-sm text-gray-500">({{ $currentEducationYear }} o'quv yili)</span>
                        @endif
                    </h3>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Saqlash
                    </button>
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
                                    Yo'nalish
                                </th>
                                <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                    Fan nomi
                                </th>
                                <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                    Kredit
                                </th>
                                <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                    OSKI sanasi
                                </th>
                                <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                    Test sanasi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php $rowIndex = 0; @endphp
                            @foreach($scheduleData as $groupHemisId => $items)
                                {{-- Guruh sarlavhasi --}}
                                <tr class="bg-blue-50">
                                    <td colspan="7" class="px-4 py-2 text-sm font-semibold text-blue-800">
                                        {{ $items->first()['group']->name }}
                                        <span class="ml-2 text-xs font-normal text-blue-600">
                                            ({{ $items->first()['specialty_name'] }})
                                        </span>
                                    </td>
                                </tr>
                                @foreach($items as $item)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm text-gray-500">
                                            {{ ++$rowIndex }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-900">
                                            {{ $item['group']->name }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-500">
                                            {{ $item['specialty_name'] }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-900">
                                            {{ $item['subject']->subject_name }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-500">
                                            {{ $item['subject']->credit }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="date" name="schedules[{{ $rowIndex }}][oski_date]"
                                                value="{{ $item['oski_date'] }}"
                                                class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="date" name="schedules[{{ $rowIndex }}][test_date]"
                                                value="{{ $item['test_date'] }}"
                                                class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        {{-- Hidden fields --}}
                                        <input type="hidden" name="schedules[{{ $rowIndex }}][group_hemis_id]" value="{{ $item['group']->group_hemis_id }}">
                                        <input type="hidden" name="schedules[{{ $rowIndex }}][subject_id]" value="{{ $item['subject']->subject_id }}">
                                        <input type="hidden" name="schedules[{{ $rowIndex }}][subject_name]" value="{{ $item['subject']->subject_name }}">
                                        <input type="hidden" name="schedules[{{ $rowIndex }}][department_hemis_id]" value="{{ $item['group']->department_hemis_id }}">
                                        <input type="hidden" name="schedules[{{ $rowIndex }}][specialty_hemis_id]" value="{{ $item['group']->specialty_hemis_id }}">
                                        <input type="hidden" name="schedules[{{ $rowIndex }}][curriculum_hemis_id]" value="{{ $item['group']->curriculum_hemis_id }}">
                                        <input type="hidden" name="schedules[{{ $rowIndex }}][semester_code]" value="{{ $selectedSemester }}">
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t bg-gray-50">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Saqlash
                    </button>
                </div>
            </div>
        </form>
        @elseif($selectedDepartment && $selectedSemester)
            <div class="p-6 text-center bg-white rounded-lg shadow">
                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Ma'lumot topilmadi</h3>
                <p class="mt-1 text-sm text-gray-500">Tanlangan filtrlar bo'yicha fanlar topilmadi.</p>
            </div>
        @else
            <div class="p-6 text-center bg-white rounded-lg shadow">
                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Filtrlang</h3>
                <p class="mt-1 text-sm text-gray-500">Fakultet va semestrni tanlab, fanlar ro'yxatini ko'ring.</p>
            </div>
        @endif
    </div>

    @push('styles')
    <style>
        input[type="date"] {
            min-width: 140px;
        }
    </style>
    @endpush

    <script>
        function academicSchedule() {
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

                    // Yo'nalishlarni yuklash
                    fetch(`{{ route($routePrefix . '.academic-schedule.get-specialties') }}?department_id=${this.departmentId}`)
                        .then(r => r.json())
                        .then(data => this.specialties = data);

                    // Semestrlarni yuklash
                    fetch(`{{ route($routePrefix . '.academic-schedule.get-semesters') }}?department_id=${this.departmentId}`)
                        .then(r => r.json())
                        .then(data => this.semesters = data);

                    // Guruhlarni yuklash
                    fetch(`{{ route($routePrefix . '.academic-schedule.get-groups') }}?department_id=${this.departmentId}`)
                        .then(r => r.json())
                        .then(data => this.groups = data);
                },

                onSpecialtyChange() {
                    this.semesterCode = '';
                    this.groupId = '';

                    // Semestrlarni qayta yuklash
                    let url = `{{ route($routePrefix . '.academic-schedule.get-semesters') }}?department_id=${this.departmentId}`;
                    if (this.specialtyId) url += `&specialty_id=${this.specialtyId}`;
                    fetch(url).then(r => r.json()).then(data => this.semesters = data);

                    // Guruhlarni qayta yuklash
                    let groupUrl = `{{ route($routePrefix . '.academic-schedule.get-groups') }}?department_id=${this.departmentId}`;
                    if (this.specialtyId) groupUrl += `&specialty_id=${this.specialtyId}`;
                    fetch(groupUrl).then(r => r.json()).then(data => this.groups = data);
                },

                onFilterChange() {
                    // Faqat UI yangilanadi, saqlash Qidirish tugmasi orqali
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
