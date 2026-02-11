<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TDTU Termiz filiali mark platformasi</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
{{--    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>--}}

</head>
<body class="font-sans antialiased bg-gray-100">
<div x-data="{ sidebarOpen: false }" class="min-h-screen flex">
    <x-sidebar x-bind:class="{ 'hidden sm:block': !sidebarOpen, 'block': sidebarOpen }" />

    <div class="flex-1 flex flex-col">
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900">{{ config('app.name', 'Talaba Tizimi') }}</h1>
            </div>
        </header>

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
