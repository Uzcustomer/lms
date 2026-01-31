<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            YN olid qaydnoma yaratish
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

                    <form id="lessonForm" action="{{ route('admin.qaytnoma.store') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="shakl" class="block text-sm font-medium text-gray-700">Qaysi shakl</label>
                                <input type="text" name="shakl" id="shakl" value="{{ request('shakl') }}"
                                    placeholder="12-shakl"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            </div>
                            <div>
                                <label for="number" class="block text-sm font-medium text-gray-700">Raqami</label>
                                <input type="text" name="number" id="number" value="{{ request('number') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            </div>
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
    $(document).ready(function() {
        $('.select2').each(function() {
            $(this).select2({
                theme: 'classic',
                width: '100%',
                allowClear: true,
                placeholder: $(this).find('option:first').text()
            }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector(
                        '.select2-container--open .select2-search__field')?.focus();
                }, 0);
            });
        });

        $('#student_ids').on('change', function(e) {
            var selectedValues = $(this).val() || [];
            if (selectedValues.includes('all')) {
                var $options = $(this).find('option:not([value="all"])');
                $options.prop('selected', true);
                $(this).find('option[value="all"]').remove();
                $(this).trigger('change.select2');
            }
        });


        const lastFilters = @json(session('last_lesson_filters', []));
        if (lastFilters.department_id) {
            $('#department_id').val(lastFilters.department_id).trigger('change');

            $.ajax({
                url: '{{ route("admin.get.groups") }}',
                type: 'GET',
                data: {
                    department_id: lastFilters.department_id
                },
                success: function(data) {
                    $('#group_id').empty().append('<option value="">Tanlang</option>');
                    $.each(data, function(index, item) {
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
                            success: function(data) {
                                $('#semester_id').empty().append(
                                    '<option value="">Tanlang</option>');
                                $.each(data, function(index, item) {
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
                                        success: function(data) {
                                            $('#subject_id').empty().append(
                                                '<option value="">Tanlang</option>'
                                            );
                                            $.each(data, function(index,
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
                                                    success: function(
                                                        data) {
                                                        $('#teacher_id')
                                                            .empty()
                                                            .append(
                                                                '<option value="">Tanlang</option>'
                                                            );
                                                        $.each(data,
                                                            function(
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

                                                        if (lastFilters
                                                            .training_type_code
                                                        ) {
                                                            $('#teacher_id')
                                                                .val(
                                                                    lastFilters
                                                                    .training_type_code
                                                                )
                                                                .trigger(
                                                                    'change'
                                                                );
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
                }
            });
        }






        $('#lessonForm').on('submit', function(e) {
            return true;
        });

        $('input[name="selected_type"]').change(function() {
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

        $('#department_id').change(function() {
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
                    success: function(data) {
                        console.log('Groups received:', data);
                        $.each(data, function(index, item) {
                            $('#group_id').append('<option value="' + item.id +
                                '">' + item.name + '</option>');
                        });

                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading groups:', error);
                        console.log(xhr.responseText);
                    }
                });
            }
        });

        $('#group_id').change(function() {
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
                        success: function(data) {
                            $('#student_id').empty().append(
                                '<option value="">Tanlang</option>');
                            $.each(data, function(index, item) {
                                $('#student_id').append('<option value="' + item
                                    .hemis_id + '">' + item.name + ' (' + item
                                    .student_id_number + ')</option>');
                            });
                        },
                        error: function(xhr, status, error) {
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
                    success: function(data) {
                        $('#semester_id').empty().append(
                            '<option value="">Tanlang</option>');
                        $.each(data, function(index, item) {
                            var selected = '';

                            $('#semester_id').append('<option value="' + item.id +
                                '" ' + selected + '>' + item.name + '</option>');
                        });
                        if ($('#semester_id').val()) {
                            $('#semester_id').trigger('change');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading semesters:', error);
                        console.log(xhr.responseText);
                    }
                });
            }
        });
        $('#semester_id').change(function() {
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
                    success: function(data) {
                        $('#subject_id').empty().append(
                            '<option value="">Tanlang</option>');
                        $.each(data, function(index, item) {
                            $('#subject_id').append('<option value="' + item.id +
                                '">' + item.name + '</option>');
                        });

                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading subjects:', error);
                        console.log(xhr.responseText);
                    }
                });
            }
        });

        // Subject change handler
        $('#subject_id').change(function() {
            var subjectId = $(this).val();
            var semesterId = $('#semester_id').val();
            var groupId = $('#group_id').val();
            console.log('Subject changed:', subjectId);

            $('#teacher_id').empty().append('<option value="">Ta\'lim turini tanlang</option>');
            $('#date_id').empty().append('<option value="">Sanani tanlang</option>');
            $('#pair_id').empty().append('<option value="">Juftliklarni tanlang</option>');
            $('#teacher').val('');

            var selectionData = getSelectionData();

            if (subjectId && semesterId && groupId) {
                $.ajax({
                    url: '{{ route("admin.get.teacher") }}',
                    type: 'GET',
                    data: {
                        group_id: groupId,
                        semester_code: semesterId,
                        subject_id: subjectId,
                        selected_type: selectionData.selected_type,
                        student_hemis_id: selectionData.student_hemis_id,
                    },
                    success: function(data) {
                        $('#teacher_id').empty().append(
                            '<option value="">Tanlang</option>');
                        $.each(data, function(index, item) {
                            $('#teacher_id').append('<option value="' + item
                                .id + '">' + item.name + '</option>');
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading training types:', error);
                        console.log(xhr.responseText);
                    }
                });
            }
        });


        @if(request('department_id'))
        $('#department_id').val('{{ request('
            department_id ') }}').trigger('change');
        @endif
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
    </style>
</x-app-layout>