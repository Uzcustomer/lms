<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Sidebar -->
        <x-admin-sidebar-menu />

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden overflow-y-auto" style="margin-left: 256px;">
            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-screen-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            {{-- Telegram tasdiqlash ogohlantirishi --}}
            @auth('teacher')
                @php
                    $authTeacher = auth()->guard('teacher')->user();
                @endphp
                @if($authTeacher && $authTeacher->phone && !$authTeacher->isTelegramVerified())
                    @php
                        $daysLeft = $authTeacher->telegramDaysLeft();
                    @endphp
                    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
                        <div class="flex items-center justify-between px-4 py-3 rounded-lg border
                            {{ $daysLeft <= 2 ? 'bg-red-50 border-red-200' : ($daysLeft <= 4 ? 'bg-yellow-50 border-yellow-200' : 'bg-blue-50 border-blue-200') }}">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2 flex-shrink-0 {{ $daysLeft <= 2 ? 'text-red-500' : ($daysLeft <= 4 ? 'text-yellow-500' : 'text-blue-500') }}" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium {{ $daysLeft <= 2 ? 'text-red-800' : ($daysLeft <= 4 ? 'text-yellow-800' : 'text-blue-800') }}">
                                        Telegram hisobingizni tasdiqlang!
                                        @if($daysLeft > 0)
                                            <span class="font-bold">{{ $daysLeft }} kun</span> qoldi.
                                        @else
                                            <span class="font-bold">Muhlat tugadi!</span>
                                        @endif
                                    </p>
                                    <p class="text-xs {{ $daysLeft <= 2 ? 'text-red-600' : ($daysLeft <= 4 ? 'text-yellow-600' : 'text-blue-600') }}">
                                        @if($daysLeft <= 0)
                                            Telegram tasdiqlanmaguncha tizimdan foydalanish cheklanadi.
                                        @else
                                            Muhlat tugagandan so'ng tizimga kirish cheklanadi.
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('teacher.complete-profile') }}"
                               class="ml-3 inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition flex-shrink-0
                               {{ $daysLeft <= 2 ? 'bg-red-600 text-white hover:bg-red-700' : ($daysLeft <= 4 ? 'bg-yellow-600 text-white hover:bg-yellow-700' : 'bg-blue-600 text-white hover:bg-blue-700') }}">
                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                </svg>
                                Tasdiqlash
                            </a>
                        </div>
                    </div>
                @endif
            @endauth

            {{-- Impersonatsiya banneri --}}
            @if(session('impersonating'))
                <div class="bg-red-600 text-white">
                    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between">
                        <span class="text-sm font-medium">
                            Siz hozir <strong>{{ session('impersonated_name') }}</strong> sifatida kirgansiz (Superadmin rejimi)
                        </span>
                        <form action="{{ route('impersonate.stop') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="ml-4 px-3 py-1 bg-white text-red-600 text-xs font-bold rounded hover:bg-red-50 transition">
                                Orqaga qaytish
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Page Content -->
            <main class="p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @yield('content')
    @livewireScripts
</body>
<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
</html>
