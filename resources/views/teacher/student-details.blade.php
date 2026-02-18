@php
    function getStatusBadge($status) {
        $statuses = [
            'pending' => ['color' => 'yellow', 'text' => 'Kutilmoqda'],
            'recorded' => ['color' => 'green', 'text' => 'Baholangan'],
            'retake' => ['color' => 'blue', 'text' => 'Qayta topshirilgan'],
            'closed' => ['color' => 'red', 'text' => 'Yopilgan'],
        ];

        return $statuses[$status] ?? ['color' => 'gray', 'text' => 'Noma\'lum'];
    }
@endphp

<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $student->full_name }} - {{ $subjectName }}
        </h2>
    </x-slot>
    @if ($errors->any())
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Xatolik yuz berdi!</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Xatolik:</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Muvaffaqiyatli:</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif


    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    #
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Dars Idsi
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Talaba
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Dars sanasi
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fan
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Baholash tizimi
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Mashg'ulot turi
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Juftlik
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Deadline
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Baho
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Holat
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amallar
                                </th>

                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($grades as $index => $grade)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->student->short_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ format_date($grade->lesson_date) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->curriculumSubject()->subject_name}}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->student->curriculum->marking_system_name}}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->training_type_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->lesson_pair_name }}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($grade->deadline)
                                            @php $deadlineExpired = now()->greaterThan($grade->deadline); @endphp
                                            <span class="{{ $deadlineExpired ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                                {{ \Carbon\Carbon::parse($grade->deadline)->format('d.m.Y H:i') }}
                                            </span>
                                            @if($deadlineExpired)
                                                <span class="text-xs text-red-500 block">Muddat o'tgan</span>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
{{--                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $grade->grade ?? 'N/A' }}</td>--}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        @if($grade->status == 'pending')
                                            {{ $grade->reason == "absent" ? "NB" : $grade->grade }}
                                        @elseif($grade->status == 'retake')
                                            {{ $grade->grade ?? "NB" }}
                                            /{{ $grade->retake_grade }}@if($grade->retake_file_path)
                                                /{{$grade->retake_file_path}}
                                            @endif
                                        @else
                                            {{ $grade->grade }}
                                        @endif</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center">
                                            @php
                                                $badge = getStatusBadge($grade->status);
                                            @endphp
                                            <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-800">
                                                {{ __($badge['text']) }}
                                            </span>
                                        </div>
                                    </td>
                                    @if($grade->status === 'pending'))
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @php
                                                $teacher = auth()->guard('teacher')->user();
                                                $studentLevel = $grade->student->level_code ?? null;
                                                $dlSettings = $studentLevel ? \App\Models\Deadline::where('level_code', $studentLevel)->first() : null;
                                                $roleAllowed = true;
                                                if ($dlSettings) {
                                                    $roleAllowed = false;
                                                    if ($teacher->hasRole('test_markazi') && $dlSettings->retake_by_test_markazi) $roleAllowed = true;
                                                    if ($teacher->hasRole('oqituvchi') && $dlSettings->retake_by_oqituvchi) $roleAllowed = true;
                                                    if ($grade->reason === 'teacher_victim') $roleAllowed = true;
                                                }
                                            @endphp
                                            @if($grade->deadline && now()->greaterThan($grade->deadline))
                                                <span class="text-red-500 text-xs">Muddat o'tgan</span>
                                            @elseif(!$roleAllowed)
                                                <span class="text-orange-500 text-xs">Ruxsat yo'q</span>
                                            @else
                                                <button onclick="openModal('{{ $grade->id }}')"
                                                        class="text-indigo-600 hover:text-indigo-900">Baho qo'yish
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="gradeModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title"
             role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                     onclick="closeModal()"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                        class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form id="gradeForm" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="mb-4">
                                <label for="grade" class="block text-gray-700 text-sm font-bold mb-2">Baho
                                    (0-100):</label>
                                <input type="number" name="grade" id="grade" min="0" max="100"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                       required>
                            </div>
                            <div class="mb-4">
                                <label for="file" class="block text-gray-700 text-sm font-bold mb-2">Fayl:</label>
                                <input type="file" name="file" id="file"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Saqlash
                            </button>
                            <button type="button" onclick="closeModal()"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Bekor qilish
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
        <script>
            function openModal(gradeId) {
                const modal = document.getElementById('gradeModal');
                const form = document.getElementById('gradeForm');
                form.action = `/teacher/student-grades/${gradeId}`;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                const modal = document.getElementById('gradeModal');
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === "Escape") {
                    closeModal();
                }
            });

            window.onclick = function (event) {
                const gradeModal = document.getElementById('gradeModal');
                if (event.target == gradeModal) {
                    closeModal();
                }
            }
        </script>
</x-teacher-app-layout>
