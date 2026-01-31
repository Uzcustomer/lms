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

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Talaba qayta topshirishlar ro\'yxati') }}
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

                <div class="p-6 bg-white border-b border-gray-200 overflow-x-auto">
                    <div class="mb-4">
                        <form action="{{ route('admin.student-performances.index', $hemisId) }}" method="GET"
                              class="flex items-center">
                            <label for="per_page" class="mr-2">Har sahifada:</label>
                            <select id="per_page" name="per_page" onchange="this.form.submit()"
                                    class="rounded border-gray-300">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option
                                        value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fan
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                O'qituvchi
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Talaba
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Mashg'ulot nomi
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Baholash
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Sana
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Sabab
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Muddat
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Baho
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Harakat
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($grades as $grade)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $grade->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-600">
                                    {{ $grade->subject_name }}
                                    <br>
                                    Ta'lim tili: {{ $grade->student->language_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $grade->teacher->full_name ?? "Yuklanmoqda"}}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $grade->student->full_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $grade->training_type_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">   {{$grade->curriculum()->marking_system_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ \Carbon\Carbon::parse($grade->lesson_date)->format('d-m-Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">       @if($grade->reason == 'teacher_victim')
                                      Ustoz xatosi
                                    @elseif($grade->status == 'low_grade')
                                       Past baho
                                    @else
                                      NB
                                    @endif</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ \Carbon\Carbon::parse($grade->deadline)->format('d-m-Y H:i:s') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($grade->status == 'pending')
                                        {{ $grade->reason == "absent" ? "NB" : $grade->grade }}
                                    @elseif($grade->status == 'retake')
                                        {{ $grade->grade ?? "NB" }}
                                        /{{ $grade->retake_grade }}@if($grade->retake_file_path)
                                            /{{$grade->retake_file_path}}
                                        @endif
                                    @else
                                        {{ $grade->grade }}
                                    @endif
                                </td>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    {{--                                    @if(($grade->status == "pending"  and $grade->training_type_code !=11) && auth()->user()->hasAnyRole(['examiner','admin']))--}}
                                    @if(($grade->status == "pending" ) && auth()->user()->hasAnyRole(['examiner','admin']))
                                        <button onclick="openModal({{ $grade->id }})"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                                            <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                 viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                            Baho qo'yish
                                        </button>
                                    @else
                                        Deadline muddati tugagan
                                    @endif
                                    {{--                                    @if(auth()->user()->hasRole('admin') and $grade->training_type_code !=11)--}}
                                    @if(auth()->user()->hasRole('admin'))
                                        <button onclick="openStatusModal({{ $grade->id }})"
                                                class="ml-2 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                                            <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                 viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                            </svg>
                                            Status o'zgartirish
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    {{ $grades->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Grade Modal -->
    <div id="gradeModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog"
         aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                 onclick="closeModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div
                class="inline-block align-center bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="gradeForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="mb-4">
                            <label for="grade" class="block text-gray-700 text-sm font-bold mb-2">Baho (0-100):</label>
                            <input type="number" name="grade" id="grade" min="0" max="100"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                   required>
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


    <!-- Status Modal -->
    <div id="statusModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog"
         aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                 onclick="closeStatusModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div
                class="inline-block align-center bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="statusForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="mb-4">
                            <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                            <select name="status" id="status"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    required>
                                <option value="pending">Kutilmoqda</option>
                                <option value="retake">Qayta topshirilgan</option>
                                {{--                                <option value="recorded">Recorded</option>--}}
                                <option value="closed">Yopilgan</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="deadline" class="block text-gray-700 text-sm font-bold mb-2">Deadline:</label>
                            <input type="date" name="deadline" id="deadline"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Saqlash
                        </button>
                        <button type="button" onclick="closeStatusModal()"
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
            form.action = `/admin/student-grades/${gradeId}`;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('gradeModal');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        function openStatusModal(gradeId) {
            const modal = document.getElementById('statusModal');
            const form = document.getElementById('statusForm');
            form.action = `/admin/student-grades/${gradeId}/status`;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeStatusModal() {
            const modal = document.getElementById('statusModal');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === "Escape") {
                closeModal();
                closeStatusModal();
            }
        });

        window.onclick = function (event) {
            const gradeModal = document.getElementById('gradeModal');
            const statusModal = document.getElementById('statusModal');
            if (event.target == gradeModal) {
                closeModal();
            }
            if (event.target == statusModal) {
                closeStatusModal();
            }
        }
    </script>
</x-app-layout>
