<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Sozlamalar
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg border border-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg border border-red-200">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex flex-col md:flex-row gap-6">
                {{-- Sidebar --}}
                <div class="w-full md:w-56 flex-shrink-0">
                    <nav class="space-y-1">
                        <a href="{{ route('admin.settings', ['tab' => 'deadlines']) }}"
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors {{ $tab === 'deadlines' ? 'bg-blue-50 text-blue-700 border-l-3 border-blue-600' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ $tab === 'deadlines' ? 'text-blue-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Muddatlar
                        </a>

                        <a href="{{ route('admin.settings', ['tab' => 'password']) }}"
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors {{ $tab === 'password' ? 'bg-blue-50 text-blue-700 border-l-3 border-blue-600' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ $tab === 'password' ? 'text-blue-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Parol sozlamalari
                        </a>

                        <a href="{{ route('admin.settings', ['tab' => 'telegram']) }}"
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors {{ $tab === 'telegram' ? 'bg-blue-50 text-blue-700 border-l-3 border-blue-600' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ $tab === 'telegram' ? 'text-blue-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            Telegram
                        </a>

                        <a href="{{ route('admin.settings', ['tab' => 'sync']) }}"
                           class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors {{ $tab === 'sync' ? 'bg-blue-50 text-blue-700 border-l-3 border-blue-600' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ $tab === 'sync' ? 'text-blue-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Sinxronizatsiya
                        </a>
                    </nav>
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    @if($tab === 'deadlines')
                        @include('admin.settings.partials.deadlines')
                    @elseif($tab === 'password')
                        @include('admin.settings.partials.password')
                    @elseif($tab === 'telegram')
                        @include('admin.settings.partials.telegram')
                    @elseif($tab === 'sync')
                        @include('admin.settings.partials.sync')
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
