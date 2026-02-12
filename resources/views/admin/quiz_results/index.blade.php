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
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">№</th>
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
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($results as $index => $result)
                                        <tr>
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
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="p-4">
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
        $('.select2').each(function() {
            $(this).select2({
                theme: 'classic',
                width: '100%',
                allowClear: true,
                placeholder: $(this).find('option:first').text()
            });
        });

        // Fayl tanlanganda nomini ko'rsatish
        $('#file-upload').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $('#file-name').text(fileName);
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
