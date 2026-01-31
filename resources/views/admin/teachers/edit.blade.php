<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('O\'qituvchini tahrirlash') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('admin.teachers.update', $teacher) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="login" class="block text-gray-700 text-sm font-bold mb-2">Login:</label>
                            <input type="text" name="login" id="login" value="{{ old('login', $teacher->login) }}"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Parol (bo'sh
                                qoldirilsa o'zgarmaydi. Kamida 6 ta belgi):</label>
                            <input type="password" name="password" id="password"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div class="mb-4">
                            <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Rol:</label>
                            <select name="role" id="role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="toggleDepartmentSelect()">
                                <option value="teacher" {{ $teacher->role == 'teacher' ? 'selected' : '' }}>O'qituvchi</option>
                                <option value="dekan" {{ $teacher->role == 'dekan' ? 'selected' : '' }}>Dekan</option>
                            </select>
                        </div>

                        <div id="department-select" class="mb-4" style="display: {{ $teacher->role_id == 'dekan' ? 'block' : 'none' }};">
                            <label for="department" class="block text-gray-700 text-sm font-bold mb-2">Fakultet:</label>
                            <select name="department_hemis_id" id="department" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                @foreach($departments as $department)
                                    <option value="{{ $department->department_hemis_id }}" {{ $teacher->department_hemis_id == $department->department_hemis_id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>


                        <div class="mb-4">
                            <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                            <select name="status" id="status"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="1" {{ $teacher->status ? 'selected' : '' }}>Faol</option>
                                <option value="0" {{ !$teacher->status ? 'selected' : '' }}>Nofaol</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Saqlash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleDepartmentSelect() {
            var roleSelect = document.getElementById('role');
            var departmentSelect = document.getElementById('department-select');
            if (roleSelect.value === 'dekan') {
                departmentSelect.style.display = 'block';
            } else {
                departmentSelect.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', toggleDepartmentSelect);
    </script>
</x-app-layout>
