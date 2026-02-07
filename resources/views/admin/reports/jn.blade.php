<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            JN o'zlashtirish hisoboti
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if(empty($currentSemesterCodes))
                        <div class="alert alert-warning text-center py-4">
                            <strong>Joriy semestr topilmadi.</strong> Semestrlarni sinxronizatsiya qiling.
                        </div>
                    @elseif($students->isEmpty())
                        <div class="alert alert-info text-center py-4">
                            Joriy semestrda JN baholari topilmadi.
                        </div>
                    @else
                        <div class="mb-4">
                            <span class="badge badge-primary" style="font-size: 0.9rem; padding: 8px 16px; background-color: #3b82f6; color: white; border-radius: 6px;">
                                Jami talabalar: {{ $students->count() }}
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="jnReportTable">
                                <thead style="background-color: #1e3a5f; color: white;">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Talaba F.I.O</th>
                                        <th>Guruh</th>
                                        <th>Kafedra</th>
                                        <th>Kurs</th>
                                        <th>Semestr</th>
                                        <th style="width: 120px;">O'rtacha baho</th>
                                        <th style="width: 100px;">Baholar soni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($students as $index => $student)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <strong>{{ $student->full_name }}</strong>
                                            </td>
                                            <td>{{ $student->group_name }}</td>
                                            <td>{{ $student->department_name }}</td>
                                            <td>{{ $student->level_name }}</td>
                                            <td>{{ $student->semester_name }}</td>
                                            <td>
                                                @if($student->avg_grade < 60)
                                                    <span class="badge" style="background-color: #ef4444; color: white; padding: 4px 12px; border-radius: 4px; font-size: 0.85rem;">
                                                        {{ $student->avg_grade }}
                                                    </span>
                                                @elseif($student->avg_grade < 75)
                                                    <span class="badge" style="background-color: #f59e0b; color: white; padding: 4px 12px; border-radius: 4px; font-size: 0.85rem;">
                                                        {{ $student->avg_grade }}
                                                    </span>
                                                @else
                                                    <span class="badge" style="background-color: #22c55e; color: white; padding: 4px 12px; border-radius: 4px; font-size: 0.85rem;">
                                                        {{ $student->avg_grade }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $student->grades_count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#jnReportTable').length) {
                $('#jnReportTable').DataTable({
                    paging: true,
                    pageLength: 50,
                    searching: true,
                    ordering: true,
                    order: [[6, 'asc']],
                    language: {
                        search: "Qidirish:",
                        lengthMenu: "_MENU_ ta ko'rsatish",
                        info: "_TOTAL_ tadan _START_ - _END_ ko'rsatilmoqda",
                        paginate: {
                            first: "Birinchi",
                            last: "Oxirgi",
                            next: "Keyingi",
                            previous: "Oldingi"
                        },
                        zeroRecords: "Ma'lumot topilmadi",
                        emptyTable: "Jadvalda ma'lumot yo'q"
                    }
                });
            }
        });
    </script>
</x-app-layout>
