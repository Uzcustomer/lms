<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Muddatlarni Ko\'rish') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="mb-4 text-lg font-semibold">Har bir kurs darajasi uchun muddatlar (kunlarda)</h2>

                    @if (session('success'))
                        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-4">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Spravka topshirish muddati:</span>
                            <span class="ml-2 text-lg font-bold text-amber-700">{{ $spravkaDays ?? 10 }} kun</span>
                        </div>
                    </div>

                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Mustaqil ta'lim topshiriq muddati sozlamalari</h3>
                        <div class="flex flex-wrap gap-6">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Muddat turi:</span>
                                <span class="ml-2 text-sm font-bold text-blue-700">
                                    @if(($mtDeadlineType ?? 'before_last') == 'before_last')
                                        Oxirgi darsdan bitta oldingi darsda
                                    @elseif($mtDeadlineType == 'last')
                                        Oxirgi darsda
                                    @elseif($mtDeadlineType == 'fixed_days')
                                        Dars sanasidan + N kun
                                    @endif
                                </span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Muddat vaqti:</span>
                                <span class="ml-2 text-sm font-bold text-blue-700">{{ $mtDeadlineTime ?? '17:00' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        Daraja</th>
                                    <th
                                        class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        Muddat (kunlar)</th>
                                    <th
                                        class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        Joriy nazorat o'tish bali</th>
                                    <th
                                        class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        Mustaqil ta'lim o'tish bali</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($deadlines as $deadline)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $deadline->level->level_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">Kurs kodi({{ $deadline->level->level_code }})
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ $deadline->deadline_days ?? 'Belgilanmagan' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ $deadline->joriy ?? 'Belgilanmagan' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ $deadline->mustaqil_talim ?? 'Belgilanmagan' }}
                                            </div>

                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('admin.deadlines.edit') }}"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Muddatlarni tahrirlash
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>