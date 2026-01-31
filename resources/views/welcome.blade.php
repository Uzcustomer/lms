<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">
<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
    <div>
        <a href="/">
            <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
        </a>
    </div>
    {{--            <h1 class="text-4xl font-bold text-blue-500 text-center mt-4 mb-4 shadow-sm">Talabalar uchun</h1>--}}

    <div class="flex flex-row gap-5 w-full sm:max-w-lg mt-6 px-6 py-4 overflow-hidden">
        <a href="{{ route('student.login') }}" class="py-4 w-full flex flex-col items-center gap-2 bg-white dark:bg-gray-800 shadow-sm rounded-lg">
            <img src="{{ asset('/image/student.webp') }}" alt="" class="size-20">
            <div class="text-lg">Student</div>
        </a>
        <a href="{{ route('teacher.login') }}" class="py-4 w-full flex flex-col items-center gap-2 bg-white dark:bg-gray-800 shadow-sm rounded-lg">
            <img src="{{ asset('/image/teacher.webp') }}" alt="" class="size-20">
            <div class="text-lg">Teacher</div>
        </a>
        <a href="{{ route('admin.login') }}" class="py-4 w-full flex flex-col items-center gap-2 bg-white dark:bg-gray-800 shadow-sm rounded-lg">
            <img src="{{ asset('/image/admin.webp') }}" alt="" class="size-20">
            <div class="text-lg">Admin</div>
        </a>
    </div>
</div>
</body>
</html>
