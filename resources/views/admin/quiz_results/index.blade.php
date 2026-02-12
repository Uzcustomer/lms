<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Quiz natijalar (Test markazi)
            </h2>
            <div class="flex items-center gap-4">
                {{-- Excel yuklab olish --}}
                <a href="{{ route('admin.quiz-results.export', request()->query()) }}"
                   class="inline-flex justify-center items-center px-4 py-2 bg-green-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 active:bg-green-700 focus:outline-none focus:ring focus:ring-green-200 transition shadow-md hover:shadow-lg">
                    Excelga yuklash
                </a>

                {{-- Diagnostika --}}
                <button type="button" id="btn-diagnostika"
                        class="inline-flex justify-center items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 active:bg-yellow-700 focus:outline-none focus:ring focus:ring-yellow-200 transition shadow-md hover:shadow-lg disabled:opacity-50"
                        disabled>
                    Diagnostika
                </button>

                {{-- Sistemaga yuklash --}}
                <button type="button" id="btn-upload"
                        class="inline-flex justify-center items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-800 focus:outline-none focus:ring focus:ring-purple-200 transition shadow-md hover:shadow-lg disabled:opacity-50"
                        disabled style="display:none;">
                    Sistemaga yuklash
                </button>

                {{-- Excel orqali import --}}
                <form action="{{ route('admin.quiz-results.import') }}" method="POST" enctype="multipart/form-data"
                      class="flex items-center">
                    @csrf
                    <div class="relative mr-2">
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" class="sr-only" id="file-upload">
                        <label for="file-upload"
                               class="cursor-pointer bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Faylni tanlang
                        </label>
                        <span id="file-name" class="ml-2 text-sm text-gray-600"></span>
                    </div>
                    <button type="submit"
                            class="inline-flex justify-center items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-200 transition shadow-md hover:shadow-lg">
                        Baholarni yuklash
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Muvaffaqiyatli!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Xato!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    @if(session('import_errors'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Yuklanmagan natijalar ({{ session('error_count') }} ta):</strong>
            <table class="min-w-full divide-y divide-gray-200 mt-2">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Attempt ID</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student ID</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fan</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Baho</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sababi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach(session('import_errors') as $err)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $err['attempt_id'] ?? '' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $err['student_id'] ?? '' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $err['student_name'] ?? '' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $err['fan_name'] ?? '' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $err['grade'] ?? '' }}</td>
                            <td class="px-4 py-2 text-sm text-red-600 font-medium">{{ $err['error'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Xatoliklar mavjud:</strong>
            <ul class="mt-3 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Diagnostika natijalari paneli --}}
    <div id="diagnostika-panel" class="mb-4" style="display:none;">
        <div id="diagnostika-loading" class="bg-blue-50 border border-blue-300 text-blue-700 px-4 py-3 rounded mb-2" style="display:none;">
            Diagnostika tekshirilmoqda...
        </div>
        <div id="diagnostika-summary" class="px-4 py-3 rounded mb-2" style="display:none;"></div>
        <div id="diagnostika-errors" style="display:none;">
            <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded">
                <strong class="font-bold">Xato natijalar:</strong>
                <table class="min-w-full divide-y divide-gray-200 mt-2">
                    <thead class="bg-red-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-red-700 uppercase">Student ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-red-700 uppercase">Talaba</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-red-700 uppercase">Fan</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-red-700 uppercase">Baho</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-red-700 uppercase">Sababi</th>
                        </tr>
                    </thead>
                    <tbody id="diagnostika-errors-body" class="bg-white divide-y divide-gray-200">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Upload natijasi --}}
    <div id="upload-result" class="mb-4" style="display:none;"></div>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                {{-- Filtr formasi --}}
                <form id="search-form" method="GET" action="{{ route('admin.quiz-results.index') }}" class="p-6 bg-gray-50">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Fakultet</label>
                            <select name="faculty" class="w-full rounded-md select2">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $fac)
                                    <option value="{{ $fac }}" {{ request('faculty') == $fac ? 'selected' : '' }}>{{ $fac }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Yo'nalish</label>
                            <input type="text" name="direction" value="{{ request('direction') }}"
                                   placeholder="Yo'nalish"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Semestr</label>
                            <select name="semester" class="w-full rounded-md select2">
                                <option value="">Barchasi</option>
                                @foreach($semesters as $sem)
                                    <option value="{{ $sem }}" {{ request('semester') == $sem ? 'selected' : '' }}>{{ $sem }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Fan nomi</label>
                            <input type="text" name="fan_name" value="{{ request('fan_name') }}"
                                   placeholder="Fan nomi"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Talaba ismi</label>
                            <input type="text" name="student_name" value="{{ request('student_name') }}"
                                   placeholder="Talaba ismi"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Student ID</label>
                            <input type="text" name="student_id" value="{{ request('student_id') }}"
                                   placeholder="Hemis ID"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Quiz turi</label>
                            <select name="quiz_type" class="w-full rounded-md select2">
                                <option value="">Barchasi</option>
                                @foreach($quizTypes as $type)
                                    <option value="{{ $type }}" {{ request('quiz_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Sanadan</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Sanagacha</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="w-full sm:w-auto">
                            <label for="per_page" class="block sm:inline text-sm font-medium text-gray-700 mr-2">Har bir sahifada:</label>
                            <select id="per_page" name="per_page"
                                    class="select2 mt-1 sm:mt-0 block w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-200 transition shadow-md hover:shadow-lg">
                            Qidirish
                        </button>
                    </div>
                </form>

                {{-- Natijalar jadvali --}}
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full">
                        @if($results->isEmpty())
                            <div class="text-gray-500 text-center py-8">
                                <p>Hozircha quiz natijalar mavjud emas.</p>
                            </div>
                        @else
                            <table class="min-w-full divide-y divide-gray-200" id="results-table">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Talaba</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fakultet</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yo'nalish</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semestr</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz turi</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shakl</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Baho</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eski baho</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boshlanish</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tugash</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($results as $index => $result)
                                        <tr id="row-{{ $result->id }}" class="result-row">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <input type="checkbox" class="row-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="{{ $result->id }}">
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $results->firstItem() + $index }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->student_id }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->student_name }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->faculty }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->direction }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->semester }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->fan_name }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->quiz_type }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $result->shakl }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">{{ $result->grade }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $result->old_grade }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $result->date_start ? $result->date_start->format('d.m.Y H:i') : '' }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $result->date_finish ? $result->date_finish->format('d.m.Y H:i') : '' }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <button type="button" class="btn-delete-row text-red-600 hover:text-red-800 font-medium" data-id="{{ $result->id }}">
                                                    O'chirish
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="p-4 flex items-center justify-between">
                                <div id="selection-info" class="text-sm text-gray-600">
                                    <span id="selected-count">0</span> ta tanlangan
                                </div>
                                {{ $results->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        var csrfToken = '{{ csrf_token() }}';
        var diagnostikaUrl = '{{ route("admin.quiz-results.diagnostika") }}';
        var uploadUrl = '{{ route("admin.quiz-results.upload") }}';
        var destroyUrlBase = '{{ url("/admin/quiz-results") }}';
        var diagnostikaRan = false;

        // Select2
        $('.select2').each(function() {
            $(this).select2({
                theme: 'classic',
                width: '100%',
                allowClear: true,
                placeholder: $(this).find('option:first').text()
            });
        });

        // Fayl nomi
        $('#file-upload').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $('#file-name').text(fileName);
        });

        // Tanlangan IDlarni olish
        function getSelectedIds() {
            var ids = [];
            $('.row-checkbox:checked').each(function() {
                ids.push(parseInt($(this).val()));
            });
            return ids;
        }

        // Tugmalar holatini yangilash
        function updateButtons() {
            var count = getSelectedIds().length;
            $('#selected-count').text(count);
            $('#btn-diagnostika').prop('disabled', count === 0);

            // Diagnostika qaytadan ishlatilishi kerak bo'lganda upload tugmasini yashirish
            if (diagnostikaRan && count > 0) {
                diagnostikaRan = false;
                $('#btn-upload').hide().prop('disabled', true);
                $('#diagnostika-panel').hide();
                $('#upload-result').hide();
            }
        }

        // Hammasi tanlash
        $('#select-all').on('change', function() {
            var checked = $(this).is(':checked');
            $('.row-checkbox').prop('checked', checked);
            updateButtons();
        });

        // Bitta checkbox o'zgarganda
        $(document).on('change', '.row-checkbox', function() {
            updateButtons();
            // Agar hechbiri tanlanmasa, select-all ni olib tashlash
            var total = $('.row-checkbox').length;
            var checked = $('.row-checkbox:checked').length;
            $('#select-all').prop('checked', total > 0 && checked === total);
        });

        // Qatorni o'chirish (is_active=0)
        $(document).on('click', '.btn-delete-row', function() {
            var id = $(this).data('id');
            var row = $('#row-' + id);

            if (!confirm("Bu natijani o'chirishni xohlaysizmi?")) return;

            $.ajax({
                url: destroyUrlBase + '/' + id,
                type: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function() {
                    row.fadeOut(300, function() { $(this).remove(); });
                    updateButtons();
                },
                error: function(xhr) {
                    alert("Xato yuz berdi: " + (xhr.responseJSON?.message || 'Server xatosi'));
                }
            });
        });

        // DIAGNOSTIKA
        $('#btn-diagnostika').on('click', function() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;

            var btn = $(this);
            btn.prop('disabled', true).text('Tekshirilmoqda...');
            $('#diagnostika-panel').show();
            $('#diagnostika-loading').show();
            $('#diagnostika-summary').hide();
            $('#diagnostika-errors').hide();
            $('#btn-upload').hide().prop('disabled', true);
            $('#upload-result').hide();

            $.ajax({
                url: diagnostikaUrl,
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                contentType: 'application/json',
                data: JSON.stringify({ ids: ids }),
                success: function(data) {
                    $('#diagnostika-loading').hide();
                    diagnostikaRan = true;

                    // Summary
                    var summaryClass, summaryText;
                    if (data.error_count === 0) {
                        summaryClass = 'bg-green-100 border border-green-400 text-green-800';
                        summaryText = 'Hammasi tayyor! ' + data.ok_count + ' ta natija muammosiz yuklanishi mumkin.';
                    } else if (data.ok_count === 0) {
                        summaryClass = 'bg-red-100 border border-red-400 text-red-800';
                        summaryText = 'Hech bir natija yuklanmaydi. ' + data.error_count + ' ta xato topildi.';
                    } else {
                        summaryClass = 'bg-yellow-100 border border-yellow-400 text-yellow-800';
                        summaryText = data.ok_count + ' ta tayyor, ' + data.error_count + ' ta xato. Xatolarni tuzating va qayta diagnostika qiling.';
                    }
                    $('#diagnostika-summary').attr('class', 'px-4 py-3 rounded mb-2 font-semibold ' + summaryClass).text(summaryText).show();

                    // Xatolar jadvali
                    if (data.error_count > 0) {
                        var tbody = $('#diagnostika-errors-body');
                        tbody.empty();
                        data.errors.forEach(function(err) {
                            tbody.append(
                                '<tr>' +
                                    '<td class="px-4 py-2 text-sm text-gray-900">' + (err.student_id || '') + '</td>' +
                                    '<td class="px-4 py-2 text-sm text-gray-900">' + (err.student_name || '') + '</td>' +
                                    '<td class="px-4 py-2 text-sm text-gray-900">' + (err.fan_name || '') + '</td>' +
                                    '<td class="px-4 py-2 text-sm text-gray-900">' + (err.grade || '') + '</td>' +
                                    '<td class="px-4 py-2 text-sm text-red-600 font-medium">' + (err.error || '') + '</td>' +
                                '</tr>'
                            );
                        });
                        $('#diagnostika-errors').show();
                    }

                    // Upload tugmasini ko'rsatish
                    if (data.ok_count > 0) {
                        $('#btn-upload').show().prop('disabled', false).text('Sistemaga yuklash (' + data.ok_count + ' ta)');
                    }
                },
                error: function(xhr) {
                    $('#diagnostika-loading').hide();
                    var msg = xhr.responseJSON?.message || 'Server xatosi';
                    $('#diagnostika-summary').attr('class', 'px-4 py-3 rounded mb-2 font-semibold bg-red-100 border border-red-400 text-red-800').text('Xato: ' + msg).show();
                },
                complete: function() {
                    btn.prop('disabled', false).text('DIAGNOSTIKA');
                }
            });
        });

        // SISTEMAGA YUKLASH
        $('#btn-upload').on('click', function() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;

            if (!confirm("Tanlangan " + ids.length + " ta natijani student_grades ga yuklashni tasdiqlaysizmi?")) return;

            var btn = $(this);
            btn.prop('disabled', true).text('Yuklanmoqda...');

            $.ajax({
                url: uploadUrl,
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                contentType: 'application/json',
                data: JSON.stringify({ ids: ids }),
                success: function(data) {
                    var html = '';
                    if (data.success_count > 0) {
                        html += '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-2">';
                        html += '<strong>Muvaffaqiyatli!</strong> ' + data.success_count + ' ta natija student_grades ga yuklandi.';
                        html += '</div>';
                    }
                    if (data.error_count > 0) {
                        html += '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-2">';
                        html += '<strong>' + data.error_count + ' ta xato:</strong><ul class="mt-1 list-disc list-inside">';
                        data.errors.forEach(function(err) {
                            html += '<li>' + (err.student_name || '') + ' — ' + (err.fan_name || '') + ': ' + (err.error || '') + '</li>';
                        });
                        html += '</ul></div>';
                    }
                    $('#upload-result').html(html).show();
                    btn.hide();
                    $('#diagnostika-panel').hide();

                    // Muvaffaqiyatli yuklangandan keyin checkboxlarni olib tashlash
                    if (data.success_count > 0) {
                        $('.row-checkbox:checked').each(function() {
                            var rowId = $(this).val();
                            // Xato bo'lmagan qatorlarni belgilash (yashil rang)
                            var hasError = false;
                            if (data.errors) {
                                data.errors.forEach(function(err) {
                                    if (err.id == rowId) hasError = true;
                                });
                            }
                            if (!hasError) {
                                $('#row-' + rowId).addClass('bg-green-50').find('.row-checkbox').prop('checked', false).prop('disabled', true);
                                $('#row-' + rowId).find('.btn-delete-row').remove();
                            }
                        });
                        updateButtons();
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'Server xatosi';
                    $('#upload-result').html(
                        '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-2">' +
                        '<strong>Xato!</strong> ' + msg + '</div>'
                    ).show();
                },
                complete: function() {
                    btn.prop('disabled', false).text('SISTEMAGA YUKLASH');
                }
            });
        });
    });
    </script>

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
    .select2-container--classic .select2-selection--single .select2-selection__arrow {
        height: 36px;
        width: 25px;
        border-left: none;
        border-radius: 0 0.375rem 0.375rem 0;
        background: transparent;
    }
    </style>
</x-app-layout>
