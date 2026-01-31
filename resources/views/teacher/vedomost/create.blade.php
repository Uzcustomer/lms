<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost yaratish
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @if (session('success'))
                        <div class="px-4 py-2 mb-4 text-green-700 bg-green-100 border border-green-400 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="px-4 py-2 mb-4 text-red-700 bg-red-100 border border-red-400 rounded">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form id="lessonForm" action="{{ route('teacher.vedomost.store') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-12 col-md-2">
                                <label class="block mb-2">Bo'lim</label>
                                <select name="department_id" id="department_id" class="w-full rounded-md select2"
                                    required>
                                    <option value="">Tanlang</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->department_hemis_id }}">{{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="block mb-2">Semestr</label>
                                <select name="semester_code" id="semester_id" class="w-full rounded-md select2"
                                    required>
                                    <option value="">Tanlang</option>
                                    @foreach($semesters as $semester)
                                        <option value="{{ $semester->code }}">{{ $semester->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="block mb-2">Shakl turi</label>
                                <select name="shakl" id="shakl" class="w-full rounded-md select2">
                                    <option value="">Tanlang</option>
                                    <option value="1">12-shakl</option>
                                    <option value="2">12-shakl(qo‘shimcha)</option>
                                    <option value="3">12a-shakl</option>
                                    <option value="4">12a-shakl(qo‘shimcha)</option>
                                    <option value="5">12b-shakl</option>
                                    <option value="6">12b-shakl(qo‘shimcha)</option>
                                    <option value="7">12-shakl (yozgi)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="block mb-2">Guruh nomi</label>
                                <input type="text" name="group_name" id="group_name" class="rounded-lg "
                                    style="width:100%" required placeholder="Guruh nomini kiriting" />
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="block mb-2">Fan nomi</label>
                                <input type="text" name="subject_name" id="subject_name" class="rounded-lg "
                                    style="width:100%" required placeholder="Fan nomini kiriting" />
                            </div>
                            <div class="mt-3 col-12 row">
                                <div class="col-6">
                                    <label for="" class="mr-2">Bittalik</label>
                                    <input type="radio" name="type" id="type" value="1" checked request>
                                </div>
                                <div class="col-6">
                                    <label for="" class="mr-2">Ikkitalik</label>
                                    <input type="radio" name="type" id="type" value="2" required>
                                </div>
                            </div>

                            <div class="col-12 col-md-1">
                                <label class="block mb-2">Joriy foiz</label>
                                <input style="width:100%;" type="text" name="jn_percend" id="jn_percend" class="rounded-lg" required />
                                <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                    <input class="form-check-input" type="checkbox" id="round_jn" name="round_jn" >
                                    <label class="mb-0 form-check-label" for="round_jn">Yaxlitlash</label>
                                </div>
                            </div>

                            <div class="col-12 col-md-1">
                                <label class="block mb-2">M ta'lim foiz</label>
                                <input style="width:100%;" type="text" name="independent_percend" id="independent_percend" class="rounded-lg" required />
                                <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                    <input class="form-check-input" type="checkbox" id="round_independent" name="round_independent" >
                                    <label class="mb-0 form-check-label" for="round_independent">Yaxlitlash</label>
                                </div>
                            </div>

                            <div class="col-12 col-md-1">
                                <label class="block mb-2">Oraliq foiz</label>
                                <input style="width:100%;" type="text" name="oraliq_percent" id="oraliq_percent" class="rounded-lg" required />
                                <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                    <input class="form-check-input" type="checkbox" id="round_oraliq" name="round_oraliq" >
                                    <label class="mb-0 form-check-label" for="round_oraliq">Yaxlitlash</label>
                                </div>
                            </div>

                            <div class="col-12 col-md-3 secend">
                                <div class="row">
                                    <div class="col-4">
                                        <label class="block mb-2">Joriy 2 foiz</label>
                                        <input type="text" name="jn_percend2" id="jn_percend2" class="rounded-lg" style="width:100%;" />
                                        <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                            <input class="form-check-input" type="checkbox" id="round_jn2" name="round_jn2" >
                                            <label class="mb-0 form-check-label" for="round_jn2">Yaxlitlash</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="block mb-2">M ta'lim 2 foiz</label>
                                        <input type="text" name="independent_percend2" id="independent_percend2" class="rounded-lg" style="width:100%;" />
                                        <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                            <input class="form-check-input" type="checkbox" id="round_independent2" name="round_independent2" >
                                            <label class="mb-0 form-check-label" for="round_independent2">Yaxlitlash</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="block mb-2">Oraliq 2 foiz</label>
                                        <input style="width:100%;" type="text" name="oraliq_percent2" id="oraliq_percent2" class="rounded-lg" />
                                        <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                            <input class="form-check-input" type="checkbox" id="round_oraliq2" name="round_oraliq2" >
                                            <label class="mb-0 form-check-label" for="round_oraliq2">Yaxlitlash</label>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="col-12 col-md-1">
                                <label class="block mb-2">OSKE foiz</label>
                                <input style="width:100%;" type="text" name="oske_percend" id="oske_percend"
                                    class="rounded-lg " required />
                                <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                    <input class="form-check-input" type="checkbox" id="round_oske" name="round_oske" >
                                    <label class="mb-0 form-check-label" for="round_oske">Yaxlitlash</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-1">
                                <label class="block mb-2">Test foiz</label>
                                <input style="width:100%;" type="text" name="exam_percend" id="exam_percend"
                                    class="rounded-lg " required />
                                <div class="gap-2 mt-2 form-check form-switch d-flex align-items-center">
                                    <input class="form-check-input" type="checkbox" id="round_exam" name="round_exam" >
                                    <label class="mb-0 form-check-label" for="round_exam">Yaxlitlash</label>
                                </div>
                            </div>
                            </br>
                            <div class="mt-3 col-12 row guruh_fan_tanlash" id="box">
                                <div class="col-12 col-md-3">
                                    <label class="block mb-2 ">Guruh</label>
                                    <select name="group_id[]" class="w-full rounded-md select2 group_id" required>
                                        <option value="">Avval bo'limni va semesterni tanlang</option>
                                    </select>
                                </div>
                                <!-- <div class="col-4">
                                    <label class="block mb-2">Talabalar</label>
                                    <select name="students[]" class="w-full rounded-md select2 students" multiple>
                                        <option value="">Avval guruhni tanlang</option>
                                    </select>
                                </div> -->
                                <div class="col-12 col-md-2">
                                    <label class="block mb-2">Fan 1</label>
                                    <select name="subject_one_id[]" class="w-full rounded-md select2 subject_id">
                                        <option value="">Avval guruhni tanlang</option>
                                    </select>
                                </div>
                                <div class="col-2 subject_secend">
                                    <label class="block mb-2">Fan 2</label>
                                    <select name="subject_secend_id[]" class="w-full rounded-md select2 subject_id2">
                                        <option value="">Avval guruhni tanlang</option>
                                    </select>
                                </div>
                                <button type="button"
                                    class="px-2 py-2 mt-4 font-bold text-red-500 rounded col-1 delete_guruh_div">X
                                </button>
                            </div>
                            <div class="mt-3 col-1">
                                <button type="button"
                                    class="px-2 py-2 font-bold text-white bg-blue-500 rounded hover:bg-blue-700"
                                    id="copyBtn">
                                    +
                                </button>
                            </div>

                        </div>

                        <div class="mt-6">
                            <button type="submit"
                                class="px-4 py-2 font-bold text-white bg-blue-500 rounded hover:bg-blue-700">
                                Saqlash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script>
        $(document).ready(function () {
            var groups = [];
            var status = 0;
            $(document).on("change", ".group_id", function () {
                var groupId = $(this).val();
                var subjectSelect = $(this).closest(".guruh_fan_tanlash").find(
                    ".subject_id"); // Shu div ichidagi selectni topish
                var subjectSelect2 = $(this).closest(".guruh_fan_tanlash").find(
                    ".subject_id2"); // Shu div ichidagi selectni topish
                var students = $(this).closest(".guruh_fan_tanlash").find(
                    ".students"); // Shu div ichidagi selectni topish

                var semester_id = $('#semester_id').val();

                if (groupId) {
                    $.ajax({
                        url: '{{ route("teacher.get-subjects") }}',
                        type: 'GET',
                        data: {
                            semester_code: semester_id,
                            group_id: groupId,
                        },
                        success: function (data) {
                            subjectSelect.empty().append(
                                '<option value="">Fan tanlang</option>'
                            ); // Eski ma'lumotlarni o'chirish
                            subjectSelect2.empty().append(
                                '<option value="">Fan tanlang</option>'
                            ); // Eski ma'lumotlarni o'chirish
                            $.each(data, function (index, item) {
                                subjectSelect.append('<option value="' + item.id +
                                    '">' + item.name + '</option>');
                                subjectSelect2.append('<option value="' + item.id +
                                    '">' + item.name + '</option>');
                            });
                        },
                        error: function (xhr, status, error) {
                            console.error('Fanlarni yuklashda xatolik:', error);
                        }
                    });
                    $.ajax({
                        url: '{{ route("teacher.get.students") }}',
                        type: 'GET',
                        data: {
                            group_id: groupId,
                        },
                        success: function (data) {
                            students.empty().append(
                                '<option value="">Fan tanlang</option>'
                            ); // Eski ma'lumotlarni o'chirish

                            $.each(data, function (index, item) {
                                students.append('<option value="' + item.hemis_id +
                                    '">' + item.name + '</option>');
                            });
                        },
                        error: function (xhr, status, error) {
                            console.error('Fanlarni yuklashda xatolik:', error);
                        }
                    });

                }
            });
            $("#copyBtn").click(function () {
                if (status) {
                    var original = $(".guruh_fan_tanlash").last();
                    original.find("select").select2("destroy"); // Eski Select2'ni yo'q qilish

                    var clone = original.clone(true, true);
                    original.after(clone);

                    // Yangi Select elementlarini tozalash va Select2 qo‘shish
                    clone.find("select").each(function () {
                        $(this).val($(this).find("option:first").val()).trigger("change");
                        $(this).select2({
                            theme: "classic",
                            width: "100%"
                        }); // Select2 yangidan qo'shiladi
                    });
                }
            });
            $(document).on("click", ".delete_guruh_div", function () {
                if (status && $(".guruh_fan_tanlash").length > 1) {
                    $(this).parent().remove();
                }
            });

            function get_group(department_id, semester_id) {
                $.ajax({
                    url: '{{ route("teacher.get.groups_semester") }}',
                    type: 'GET',
                    data: {
                        department_id: department_id,
                        semester_id: semester_id,
                    },
                    success: function (data) {

                        $.each(data, function (index, item) {
                            $('.group_id').append('<option value="' + item.id +
                                '">' + item.name + '</option>');
                        });
                        status = 1;
                    },
                    error: function (xhr, status, error) {
                        console.error('Error loading groups:', error);
                        console.log(xhr.responseText);
                    }
                });
            }
            $('.select2').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text()
                }).on('select2:open', function () {
                    setTimeout(() => {
                        document.querySelector(
                            '.select2-container--open .select2-search__field')?.focus();
                    }, 0);
                });
            });
            const lastFilters = @json(session('vedomost', []));
            if (lastFilters.department_id) {
                $('#department_id').val(lastFilters.department_id).trigger('change');
            }
            if (lastFilters.semester_code) {
                $('#semester_id').val(lastFilters.semester_code).trigger('change');
            }
            if (lastFilters.department_id && lastFilters.semester_code) {
                get_group(lastFilters.department_id, lastFilters.semester_code)
            }
            if (lastFilters.shakl) {
                $('#shakl').val(lastFilters.shakl).trigger('change');
            }
            if (lastFilters.group_name) {
                $('#group_name').val(lastFilters.group_name).trigger('change');
            }
            if (lastFilters.subject_name) {
                $('#subject_name').val(lastFilters.subject_name).trigger('change');
            }
            if (lastFilters.type) {
                $('input[name="type"][value="' + lastFilters.type + '"]').prop('checked', true).trigger('change');
            }
            if (lastFilters.exam_percend) {
                $('#exam_percend').val(lastFilters.exam_percend).trigger('change');
            }
            if (lastFilters.oske_percend) {
                $('#oske_percend').val(lastFilters.oske_percend).trigger('change');
            }
            if (lastFilters.oraliq_percent) {
                $('#oraliq_percent').val(lastFilters.oraliq_percent).trigger('change');
            }
            if (lastFilters.jn_percend) {
                $('#jn_percend').val(lastFilters.jn_percend).trigger('change');
            }
            if (lastFilters.independent_percend) {
                $('#independent_percend').val(lastFilters.independent_percend).trigger('change');
            }
            if (lastFilters.jn_percend2) {
                $('#jn_percend2').val(lastFilters.jn_percend2).trigger('change');
            }
            if (lastFilters.independent_percend2) {
                $('#independent_percend2').val(lastFilters.independent_percend2).trigger('change');
            }
            if (lastFilters.round_exam) {
                $('#round_exam').val(lastFilters.round_exam).trigger('change');
            }
            if (lastFilters.round_oske) {
                $('#round_oske').val(lastFilters.round_oske).trigger('change');
            }
            if (lastFilters.round_oraliq) {
                $('#round_oraliq').val(lastFilters.round_oraliq).trigger('change');
            }
            if (lastFilters.round_jn) {
                $('#round_jn').val(lastFilters.round_jn).trigger('change');
            }
            if (lastFilters.round_independent) {
                $('#round_independent').val(lastFilters.round_independent).trigger('change');
            }
            if (lastFilters.round_jn2) {
                $('#round_jn2').val(lastFilters.round_jn2).trigger('change');
            }
            if (lastFilters.round_independent2) {
                $('#round_independent2').val(lastFilters.round_independent2).trigger('change');
            }


            $('#department_id').change(function () {
                var departmentId = $(this).val();
                var semester_id = $('#semester_id').val();

                if (departmentId && semester_id) {
                    get_group(departmentId, semester_id)
                }
            });
            $('#semester_id').change(function () {
                var semesterId = $(this).val();
                var departmentId = $('#department_id').val();
                // $('#subject_id').empty().append('<option value="">Fanni tanlang</option>');

                if (semesterId && departmentId) {
                    get_group(departmentId, semesterId)

                }
            });

            // Subject change handler

            @if(request('department_id'))
                $('#department_id').val('{{ request('
                department_id ') }}').trigger('change');
            @endif
            $(".students").select2({
                theme: "classic",
                width: "100%",
                closeOnSelect: false // Tanlagandan keyin yopilib qolmasligi uchun
            });

            // Har safar element tanlanganda ro‘yxatdan yo‘qotish (agar kerak bo‘lsa)
            // $(".students").on("select2:unselect", function(e) {
            //     $(this).find('option[value="' + e.params.data.id + '"]').remove();
            // });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {


            const radios = document.querySelectorAll('input[name="type"]');

            function toggleVisibility() {
                const subject_blog = document.querySelectorAll('.subject_secend');


                const secend = document.querySelector('.secend');
                const selectedValue = document.querySelector('input[name="type"]:checked').value;

                subject_blog.forEach(el => {
                    el.style.display = selectedValue === "1" ? "none" : "block";
                });

                secend.style.display = selectedValue === "1" ? "none" : "block";
            }

            // Hodisani (event) qo‘shish
            radios.forEach(radio => {
                radio.addEventListener("change", toggleVisibility);
            });

            // Sahifa yuklanganda ham ishlashi uchun chaqirib qo‘yamiz
            toggleVisibility();
        });
    </script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        .select2-container--classic .select2-selection--single {
            height: 38px;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            background: white;
        }

        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: 12px;
            padding-right: 45px;
            color: #374151;
        }

        .select2-container--classic .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 30px;
            font-size: 22px;
            font-weight: 500;
            color: #4B5563;
            margin: 0;
            height: 36px;
            line-height: 36px;
            padding: 0 5px;
        }

        .select2-container--classic .select2-selection--single .select2-selection__clear:hover {
            color: #1F2937;
        }

        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 36px;
            width: 25px;
            border-left: none;
            border-radius: 0 0.375rem 0.375rem 0;
            background: transparent;
            position: absolute;
            right: 0;
            top: 0;
        }

        .select2-container--classic .select2-selection--single .select2-selection__arrow b {
            border-color: #6B7280 transparent transparent transparent;
        }

        .select2-container--classic.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: transparent transparent #6B7280 transparent;
        }

        .select2-container--classic .select2-selection--single.select2-selection--clearable .select2-selection__arrow {
            right: 0;
        }

        .hide_talaba {
            display: none;
        }
    </style>
</x-teacher-app-layout>
