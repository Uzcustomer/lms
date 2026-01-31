<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Test yaratish
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @if (session('success'))
                        <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form id="lessonForm" action="{{ route('admin.examtest.store') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-2">Bo'lim</label>
                                <select name="department_id" id="department_id" class="w-full rounded-md select2">
                                    <option value="">Tanlang</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->department_hemis_id }}">{{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class=" block mb-2">Guruh</label>
                                <select name="group_id" id="group_id" class="w-full rounded-md select2">
                                    <option value="">Avval bo'limni tanlang</option>
                                </select>
                            </div>


                            <div>
                                <label class="block mb-2">Semestr</label>
                                <select name="semester_code" id="semester_id" class="w-full rounded-md select2">
                                    <option value="">Avval guruhni tanlang</option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-2">Fan</label>
                                <select name="subject_id" id="subject_id" class="w-full rounded-md select2">
                                    <option value="">Avval semestrni tanlang</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2">Sana</label>
                                <input type="date" name="lesson_date" id="lesson_date"
                                    class="border-2 border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block mb-2">Shakl turi</label>
                                <select name="shakl" id="shakl" class="w-full rounded-md select2">
                                    <option value="">Avval fan tanlang</option>
                                </select>
                            </div>
                            <div id="show_talaba" class="hide_talaba">
                                <label class="block mb-2">Talaba</label>
                                <select name="students[]" id="students" class="w-full rounded-md select2" multiple>
                                    <option value="">Avval shakl tanlang</option>
                                </select>
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <div class="mt-4">
                                    <label for="lesson_file" class="block mb-2">
                                        Fayl yuklash
                                    </label>

                                    <div
                                        class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 px-6 py-10">
                                        <div class="text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-300" viewBox="0 0 24 24"
                                                fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z"
                                                    clip-rule="evenodd" />
                                            </svg>

                                            <div class="mt-4 flex text-lg leading-6 text-gray-600">
                                                <label for="lesson_file"
                                                    class="relative cursor-pointer rounded-md bg-white font-semibold text-blue-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-blue-600 focus-within:ring-offset-2 hover:text-blue-500">
                                                    <span>Faylni yuklang</span>
                                                    <input type="file" name="lesson_file" id="lesson_file"
                                                        class="sr-only">
                                                </label>
                                                <p class="pl-1">yoki bu yerga tashlang</p>
                                            </div>

                                            <div id="selected-file" class="mt-2 text-sm text-gray-600 hidden">
                                                <span class="font-medium text-blue-600" id="file-name"></span>
                                                <button type="button" id="remove-file"
                                                    class="ml-2 text-red-600 hover:text-red-800">
                                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>

                                            <p class="text-xs leading-5 text-gray-600 mt-2">PDF, DOC, DOCX, XLS, XLSX,
                                                PNG, JPG formatdagi fayllar (maksimal 10MB)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="mt-6">
                            <button type="submit"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
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
            $("#students").select2({
                theme: "classic",
                width: "100%",
                closeOnSelect: false // Tanlagandan keyin yopilib qolmasligi uchun
            });

            // Har safar element tanlanganda ro‘yxatdan yo‘qotish (agar kerak bo‘lsa)
            $("#students").on("select2:unselect", function (e) {
                $(this).find('option[value="' + e.params.data.id + '"]').remove();
            });
            // $('#student_ids').on('change', function (e) {
            //     var selectedValues = $(this).val() || [];
            //     if (selectedValues.includes('all')) {
            //         var $options = $(this).find('option:not([value="all"])');
            //         $options.prop('selected', true);
            //         $(this).find('option[value="all"]').remove();
            //         $(this).trigger('change.select2');
            //     }
            // });


            const lastFilters = @json(session('exam_test', []));
            if (lastFilters.lesson_date) {
                $('#lesson_date').val(lastFilters.lesson_date).trigger('change');
            }
            if (lastFilters.department_id) {
                $('#department_id').val(lastFilters.department_id).trigger('change');

                $.ajax({
                    url: '{{ route("admin.get.groups") }}',
                    type: 'GET',
                    data: {
                        department_id: lastFilters.department_id
                    },
                    success: function (data) {
                        $('#group_id').empty().append('<option value="">Tanlang</option>');
                        $.each(data, function (index, item) {
                            $('#group_id').append('<option value="' + item.id + '">' + item
                                .name + '</option>');
                        });

                        if (lastFilters.group_id) {
                            $('#group_id').val(lastFilters.group_id).trigger('change');

                            $.ajax({
                                url: '{{ route("admin.get.semesters") }}',
                                type: 'GET',
                                data: {
                                    group_id: lastFilters.group_id,
                                    selected_type: 'group'
                                },
                                success: function (data) {
                                    $('#semester_id').empty().append(
                                        '<option value="">Tanlang</option>');
                                    $.each(data, function (index, item) {
                                        $('#semester_id').append('<option value="' +
                                            item.id + '">' + item.name +
                                            '</option>');
                                    });

                                    if (lastFilters.semester_code) {
                                        $('#semester_id').val(lastFilters.semester_code)
                                            .trigger('change');

                                        $.ajax({
                                            url: '{{ route("admin.get.subjects") }}',
                                            type: 'GET',
                                            data: {
                                                group_id: lastFilters.group_id,
                                                semester_code: lastFilters
                                                    .semester_code,
                                                selected_type: 'group'
                                            },
                                            success: function (data) {
                                                $('#subject_id').empty().append(
                                                    '<option value="">Tanlang</option>'
                                                );
                                                $.each(data, function (index,
                                                    item) {
                                                    $('#subject_id')
                                                        .append(
                                                            '<option value="' +
                                                            item.id +
                                                            '">' + item
                                                                .name +
                                                            '</option>'
                                                        );
                                                });

                                                if (lastFilters.subject_id) {
                                                    $('#subject_id').val(
                                                        lastFilters
                                                            .subject_id)
                                                        .trigger('change');

                                                    $.ajax({
                                                        url: '{{ route("admin.get.teacher") }}',
                                                        type: 'GET',
                                                        data: {
                                                            group_id: lastFilters
                                                                .group_id,
                                                            semester_code: lastFilters
                                                                .semester_code,
                                                            subject_id: lastFilters
                                                                .subject_id,
                                                            selected_type: 'group'
                                                        },
                                                        success: function (
                                                            data) {
                                                            $('#teacher_id')
                                                                .empty()
                                                                .append(
                                                                    '<option value="">Tanlang</option>'
                                                                );
                                                            $.each(data,
                                                                function (
                                                                    index,
                                                                    item
                                                                ) {
                                                                    $('#teacher_id')
                                                                        .append(
                                                                            '<option value="' +
                                                                            item
                                                                                .id +
                                                                            '">' +
                                                                            item
                                                                                .name +
                                                                            '</option>'
                                                                        );
                                                                }
                                                            );


                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }
                });
            }






            $('#lessonForm').on('submit', function (e) {
                return true;
            });

            $('input[name="selected_type"]').change(function () {
                if (this.value === 'student') {
                    $('#student_select_div').show();
                } else {
                    $('#student_select_div').hide();
                    // Clear student selection when switching to group
                    $('#student_id').val(null).trigger('change');
                }
                $('#semester_id').empty().append('<option value="">Semestrni tanlang</option>');
                $('#subject_id').empty().append('<option value="">Fanni tanlang</option>');
                $('#teacher_id').empty().append('<option value="">Ta\'lim turini tanlang</option>');
                $('#date_id').empty().append('<option value="">Sanani tanlang</option>');
                $('#pair_id').empty().append('<option value="">Juftliklarni tanlang</option>');
                $('#teacher').val('');
            });

            function getSelectionData() {
                var selectedType = $('input[name="selected_type"]:checked').val();
                var studentHemisId = $('#student_id').val();
                return {
                    selected_type: selectedType,
                    student_hemis_id: selectedType === 'student' ? studentHemisId : null,
                };
            }

            $('#department_id').change(function () {
                var departmentId = $(this).val();
                console.log('Department changed:', departmentId);

                $('#group_id').empty().append('<option value="">Guruhni tanlang</option>');
                $('#student_id').empty().append('<option value="">Avval guruhni tanlang</option>');
                $('#semester_id').empty().append('<option value="">Semestrni tanlang</option>');
                $('#subject_id').empty().append('<option value="">Fanni tanlang</option>');
                $('#teacher_id').empty().append('<option value="">Ta\'lim turini tanlang</option>');

                if (departmentId) {
                    $.ajax({
                        url: '{{ route("admin.get.groups") }}',
                        type: 'GET',
                        data: {
                            department_id: departmentId
                        },
                        success: function (data) {
                            console.log('Groups received:', data);
                            $.each(data, function (index, item) {
                                $('#group_id').append('<option value="' + item.id +
                                    '">' + item.name + '</option>');
                            });

                        },
                        error: function (xhr, status, error) {
                            console.error('Error loading groups:', error);
                            console.log(xhr.responseText);
                        }
                    });
                }
            });

            $('#group_id').change(function () {
                var groupId = $(this).val();

                $('#student_id').empty().append('<option value="">Avval guruhni tanlang</option>');
                $('#semester_id').empty().append('<option value="">Semestrni tanlang</option>');
                $('#subject_id').empty().append('<option value="">Fanni tanlang</option>');
                $('#teacher_id').empty().append('<option value="">Ta\'lim turini tanlang</option>');

                var selectionData = getSelectionData();

                if (groupId) {
                    if (selectionData.selected_type === 'student') {
                        $.ajax({
                            url: '{{ route("admin.get.students") }}',
                            type: 'GET',
                            data: {
                                group_id: groupId
                            },
                            success: function (data) {
                                $('#student_id').empty().append(
                                    '<option value="">Tanlang</option>');
                                $.each(data, function (index, item) {
                                    $('#student_id').append('<option value="' + item
                                        .hemis_id + '">' + item.name + ' (' + item
                                            .student_id_number + ')</option>');
                                });
                            },
                            error: function (xhr, status, error) {
                                console.error('Error loading students:', error);
                                console.log(xhr.responseText);
                            }
                        });
                    }

                    $.ajax({
                        url: '{{ route("admin.get.semesters") }}',
                        type: 'GET',
                        data: {
                            group_id: groupId,
                            selected_type: selectionData.selected_type,
                            student_hemis_id: selectionData.student_hemis_id,
                        },
                        success: function (data) {
                            $('#semester_id').empty().append(
                                '<option value="">Tanlang</option>');
                            $.each(data, function (index, item) {
                                var selected = '';

                                $('#semester_id').append('<option value="' + item.id +
                                    '" ' + selected + '>' + item.name + '</option>');
                            });
                            if ($('#semester_id').val()) {
                                $('#semester_id').trigger('change');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error loading semesters:', error);
                            console.log(xhr.responseText);
                        }
                    });
                }
            });
            $('#semester_id').change(function () {
                var semesterId = $(this).val();
                var groupId = $('#group_id').val();
                console.log('Semester changed:', semesterId);

                $('#subject_id').empty().append('<option value="">Fanni tanlang</option>');
                $('#teacher_id').empty().append('<option value="">Ta\'lim turini tanlang</option>');
                $('#date_id').empty().append('<option value="">Sanani tanlang</option>');
                $('#pair_id').empty().append('<option value="">Juftliklarni tanlang</option>');
                $('#teacher').val('');

                var selectionData = getSelectionData();

                if (semesterId && groupId) {
                    $.ajax({
                        url: '{{ route("admin.get.subjects") }}',
                        type: 'GET',
                        data: {
                            semester_code: semesterId,
                            group_id: groupId,
                            selected_type: selectionData.selected_type,
                            student_hemis_id: selectionData.student_hemis_id,
                        },
                        success: function (data) {
                            $('#subject_id').empty().append(
                                '<option value="">Tanlang</option>');
                            $.each(data, function (index, item) {
                                $('#subject_id').append('<option value="' + item.id +
                                    '">' + item.name + '</option>');
                            });

                        },
                        error: function (xhr, status, error) {
                            console.error('Error loading subjects:', error);
                            console.log(xhr.responseText);
                        }
                    });
                }
            });
            $('#subject_id').change(function () {
                const element = document.getElementById('show_talaba');
                if (element) {
                    element.classList.add('hide_talaba');
                }
                var subject_id = $(this).val();
                var groupId = $('#group_id').val();
                var semester_id = $('#semester_id').val();
                console.log('Semester changed:', groupId, semester_id, subject_id);



                var selectionData = getSelectionData();

                if (semester_id && groupId && subject_id) {
                    $.ajax({
                        url: '{{ route("admin.get-shakl") }}',
                        type: 'GET',
                        data: {
                            semester_code: semester_id,
                            subject_id: subject_id,
                            group_id: groupId,
                        },
                        success: function (data) {
                            $('#shakl').empty().append(
                                '<option value="">Tanlang</option>');
                            $.each(data, function (index, item) {
                                $('#shakl').append('<option value="' + item.id +
                                    '">' + item.name + '</option>');
                            });

                        },
                        error: function (xhr, status, error) {
                            console.error('Error loading subjects:', error);
                            console.log(xhr.responseText);
                        }
                    });
                }
            });
            $('#shakl').change(function () {
                var shakl = $(this).val();
                var subject_id = $('#subject_id').val();
                var groupId = $('#group_id').val();
                var semester_id = $('#semester_id').val();
                var selectionData = getSelectionData();

                if (semester_id && groupId && subject_id && shakl) {
                    $.ajax({
                        url: '{{ route("admin.get-students-shakl") }}',
                        type: 'GET',
                        data: {
                            semester_code: semester_id,
                            subject_id: subject_id,
                            shakl: shakl,
                            group_id: groupId,
                        },
                        success: function (data) {
                            if (data.length > 0) {
                                const element = document.getElementById('show_talaba');
                                if (element) {
                                    element.classList.remove('hide_talaba');
                                }
                            }
                            $('#students').empty().append(
                                '<option value="">Tanlang</option>');
                            $.each(data, function (index, item) {
                                $('#students').append('<option value="' + item
                                    .hemis_id +
                                    '">' + item.full_name + '</option>');
                            });

                        },
                        error: function (xhr, status, error) {
                            console.error('Error loading subjects:', error);
                            console.log(xhr.responseText);
                        }
                    });
                }
            });

            // Subject change handler

            @if(request('department_id'))
                $('#department_id').val('{{ request('
                department_id ') }}').trigger('change');
            @endif
        });
    </script>

    <script>
        document.getElementById('lesson_file').addEventListener('change', function (e) {
            const file = e.target.files[0];
            const fileNameElement = document.getElementById('file-name');
            const selectedFileElement = document.getElementById('selected-file');

            if (file) {
                fileNameElement.textContent = file.name;
                selectedFileElement.classList.remove('hidden');
            }
        });

        document.getElementById('remove-file').addEventListener('click', function () {
            const fileInput = document.getElementById('lesson_file');
            const selectedFileElement = document.getElementById('selected-file');

            fileInput.value = '';
            selectedFileElement.classList.add('hidden');
        });

        const dropZone = document.querySelector('.border-dashed');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('border-blue-500', 'bg-blue-50');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const file = dt.files[0];
            const fileInput = document.getElementById('lesson_file');

            fileInput.files = dt.files;
            document.getElementById('file-name').textContent = file.name;
            document.getElementById('selected-file').classList.remove('hidden');
        }
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
</x-app-layout>