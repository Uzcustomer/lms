<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Akademik mobillik</h1>
                    <p class="mt-1 text-sm text-slate-500">Talabani toping va akademik mobillik arizasini yuboring</p>
                </div>
                <a href="{{ route('admin.academic-mobility.applications') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                    </svg>
                    Arizalar
                </a>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div class="font-semibold">Arizani yuborishda xatolik:</div>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('admin.academic-mobility.index') }}"
                      class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Qidiruv</label>
                        <input name="search" value="{{ request('search') }}" placeholder="F.I.Sh, HEMIS ID, talaba ID yoki telefon..."
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Ta'lim turi</label>
                        <select name="education_type" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($filters['educationTypes'] as $item)
                                <option value="{{ $item->education_type_code }}" @selected((string) request('education_type') === (string) $item->education_type_code)>{{ $item->education_type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Fakultet</label>
                        <select name="department" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($filters['departments'] as $item)
                                <option value="{{ $item->department_id }}" @selected((string) request('department') === (string) $item->department_id)>{{ $item->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Yo'nalish</label>
                        <select name="specialty" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($filters['specialties'] as $item)
                                <option value="{{ $item->specialty_id }}" @selected((string) request('specialty') === (string) $item->specialty_id)>{{ $item->specialty_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Kurs</label>
                        <select name="level_code" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($filters['levels'] as $item)
                                <option value="{{ $item->level_code }}" @selected((string) request('level_code') === (string) $item->level_code)>{{ $item->level_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Semestr</label>
                        <select name="semester_code" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($filters['semesters'] as $item)
                                <option value="{{ $item->semester_code }}" @selected((string) request('semester_code') === (string) $item->semester_code)>{{ $item->semester_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Guruh</label>
                        <select name="group" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($filters['groups'] as $item)
                                <option value="{{ $item->group_id }}" @selected((string) request('group') === (string) $item->group_id)>{{ $item->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Talaba holati</label>
                        <select name="student_status" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($filters['statuses'] as $item)
                                <option value="{{ $item->student_status_code }}" @selected((string) $selectedStatus === (string) $item->student_status_code)>{{ $item->student_status_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Sahifada</label>
                        <select name="per_page" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach([10, 25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2">
                        <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">Filtrlash</button>
                        <a href="{{ route('admin.academic-mobility.index') }}"
                           class="rounded-lg bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">Tozalash</a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Talaba</th>
                                <th class="px-4 py-3">Fakultet</th>
                                <th class="px-4 py-3">Yo'nalish</th>
                                <th class="px-4 py-3">Kurs / semestr</th>
                                <th class="px-4 py-3">Guruh</th>
                                <th class="px-4 py-3">Telefon</th>
                                <th class="px-4 py-3 text-center">Amal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($students as $student)
                                <tr class="hover:bg-blue-50/40">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-800">{{ $student->full_name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">HEMIS: {{ $student->hemis_id }} · ID: {{ $student->student_id_number }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $student->department_name }}</td>
                                    <td class="max-w-xs whitespace-normal px-4 py-3 text-slate-600">{{ $student->specialty_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $student->level_name }} · {{ $student->semester_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $student->group_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $student->phone ?: '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button"
                                                class="rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                                data-student-id="{{ $student->id }}"
                                                data-student-name="{{ $student->full_name }}"
                                                data-student-number="{{ $student->student_id_number }}"
                                                data-student-phone="{{ $student->phone }}"
                                                onclick="openMobilityModal(this)">
                                            Ariza berish
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-14 text-center text-slate-500">Filtr bo'yicha talabalar topilmadi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($students->hasPages())
                    <div class="border-t border-slate-200 px-4 py-3">{{ $students->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <div id="mobility-modal" class="fixed inset-0 z-[100] items-center justify-center bg-slate-900/60 p-4" style="display:none;" role="dialog" aria-modal="true">
        <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-gradient-to-r from-blue-700 to-blue-500 px-5 py-4 text-white">
                <div>
                    <h2 class="text-lg font-bold">Akademik mobillik arizasi</h2>
                    <p id="mobility-student-meta" class="mt-0.5 text-xs text-blue-100"></p>
                </div>
                <button type="button" onclick="closeMobilityModal()" class="rounded-full p-2 transition hover:bg-white/15" aria-label="Yopish">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.academic-mobility.store') }}" class="space-y-4 p-5">
                @csrf
                <input type="hidden" name="student_id" id="mobility-student-id">
                <div>
                    <label for="mobility-phone" class="mb-1 block text-sm font-semibold text-slate-700">Talaba telefon raqami</label>
                    <input id="mobility-phone" name="phone" required maxlength="50" placeholder="+998 90 123 45 67"
                           class="w-full rounded-lg border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="mobility-reason" class="mb-1 block text-sm font-semibold text-slate-700">Ariza berish sababi</label>
                    <textarea id="mobility-reason" name="reason" required minlength="5" maxlength="3000" rows="5"
                              placeholder="Akademik mobillik arizasining sababini kiriting..."
                              class="w-full resize-y rounded-lg border-slate-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeMobilityModal()"
                            class="rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">Bekor qilish</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12l14-7-4 14-3-6-7-1z"/>
                        </svg>
                        Yuborish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMobilityModal(button) {
            document.getElementById('mobility-student-id').value = button.dataset.studentId;
            document.getElementById('mobility-phone').value = button.dataset.studentPhone || '';
            document.getElementById('mobility-student-meta').textContent =
                button.dataset.studentName + ' · ID: ' + (button.dataset.studentNumber || '-');
            document.getElementById('mobility-reason').value = '';
            document.getElementById('mobility-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('mobility-phone').focus(), 50);
        }

        function closeMobilityModal() {
            document.getElementById('mobility-modal').style.display = 'none';
            document.body.style.overflow = '';
        }

        document.getElementById('mobility-modal').addEventListener('click', function (event) {
            if (event.target === this) closeMobilityModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMobilityModal();
        });
    </script>
</x-app-layout>
