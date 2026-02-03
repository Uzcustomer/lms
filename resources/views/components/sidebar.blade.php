@php
    $studentUser = Auth::guard('student')->user();
    $teacherUser = Auth::guard('teacher')->user();
    $webUser = Auth::user();
    $isStudent = (bool) $studentUser;
    $isTeacher = (bool) $teacherUser;
    $isAdminPanel = $webUser && $webUser->hasAnyRole(['admin', 'teacher', 'examiner', 'dekan']);
    $currentUser = $studentUser ?? $teacherUser ?? $webUser;
    $logoutRoute = $isStudent ? route('student.logout') : ($isTeacher ? route('teacher.logout') : route('admin.logout'));
@endphp

<div x-data="{ open: true, openSchedule: false }"
     :class="{'w-64': open, 'w-20': !open}"
     class="bg-gray-800 text-white flex flex-col h-screen transition-all duration-300 ease-in-out relative">
    <!-- Sidebar Header -->
    <div class="p-4 flex justify-between items-center">
        <h2 x-show="open" class="text-xl font-bold">
            @if ($isAdminPanel)
                Admin Panel
            @elseif ($isTeacher)
                Teacher Panel
            @else
                Student TTATF
            @endif
        </h2>
        <button @click="open = !open" class="text-white">
            <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <svg x-show="open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto">
        <ul class="space-y-2 py-4">
            @if ($isAdminPanel)
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span x-show="open">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.students.index') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1115 0 7.5 7.5 0 01-15 0zm7.5 4.5v3m0 0h4.5m-4.5 0H7.5" />
                        </svg>
                        <span x-show="open">Students</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.teachers.index') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75a3.75 3.75 0 01-7.5 0m7.5 0a3.75 3.75 0 00-7.5 0m7.5 0h1.5a2.25 2.25 0 002.25-2.25V9.75m-12 9h-1.5a2.25 2.25 0 01-2.25-2.25V9.75m12 0A2.25 2.25 0 0012 7.5h-1.5A2.25 2.25 0 008.25 9.75m9 0h-9" />
                        </svg>
                        <span x-show="open">Teachers</span>
                    </a>
                </li>
            @elseif ($isTeacher)
                <li>
                    <a href="{{ route('teacher.dashboard') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span x-show="open">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('teacher.students') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1115 0 7.5 7.5 0 01-15 0zm7.5 4.5v3m0 0h4.5m-4.5 0H7.5" />
                        </svg>
                        <span x-show="open">Students</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('teacher.vedomost.index') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span x-show="open">Vedomost</span>
                    </a>
                </li>
            @else
                <li>
                    <a href="{{ route('student.dashboard') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span x-show="open">Dashboard</span>
                    </a>
                </li>

                <!-- Schedule Group -->
                <li x-data="{ openSchedule: false }">
                    <button @click="openSchedule = !openSchedule" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <div class="flex items-center space-x-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            <span x-show="open">Schedule</span>
                        </div>
                        <svg :class="{'rotate-180': openSchedule}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 transition-transform duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <ul x-show="openSchedule" x-transition class="ml-6 mt-2 space-y-2">
                        <li>
                            <a href="{{ route('student.schedule') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                                <span x-show="open">Schedule</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('student.attendance') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                                </svg>
                                <span x-show="open">Attendance</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('student.subjects') }}" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                </svg>
                                <span x-show="open">Subjects</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Settings -->
                <li>
                    <button class="w-full flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span x-show="open">Settings</span>
                    </button>
                </li>

                <!-- Notifications -->
                <li>
                    <a href="#" class="flex items-center space-x-2 px-4 py-2 hover:bg-gray-700 rounded-lg transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span x-show="open">Notifications</span>
                    </a>
                </li>
            @endif
        </ul>
    </nav>

    <!-- User Info and Logout -->
    <div class="p-4 mt-auto border-t border-gray-700">
        <div x-show="open" class="flex items-center space-x-2 mb-4">
            <span class="font-semibold">{{ $currentUser?->full_name ?? $currentUser?->name ?? 'User' }}</span>
        </div>
        <form method="POST" action="{{ $logoutRoute }}">
            @csrf
            <button type="submit" class="w-full flex items-center justify-center space-x-2 bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-2 transition duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
                <span x-show="open">Logout</span>
            </button>
        </form>
    </div>
</div>
