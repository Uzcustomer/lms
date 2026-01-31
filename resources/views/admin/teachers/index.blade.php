<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('O\'qituvchilar') }}
        </h2>
    </x-slot>

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
                    <div class="flex justify-between mb-4">
                        <form action="{{ route('admin.teachers.index') }}" method="GET" class="flex items-center">
                            <input type="text" name="search" placeholder="Qidirish..."
                                   class="rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <button type="submit"
                                    class="ml-2 px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition duration-300">
                                <i class="fas fa-search mr-2"></i>{{ __('Qidirish') }}
                            </button>
                        </form>
                        {{--                        <form action="{{ route('admin.teachers.import') }}" method="POST">--}}
                        {{--                            @csrf--}}
                        {{--                            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition duration-300">--}}
                        {{--                                <i class="fas fa-file-import mr-2"></i>{{ __('Import') }}--}}
                        {{--                            </button>--}}
                        {{--                        </form>--}}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                               <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Login
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    F.I.O
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kafedra
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Lavozim
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amallar
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($teachers as $teacher)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $teacher->login }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $teacher->full_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $teacher->department }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $teacher->staff_position }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $teacher->status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $teacher->status ? 'Faol' : 'Nofaol' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('admin.teachers.edit', $teacher) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i> Tahrirlash
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $teachers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
