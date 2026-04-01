<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>TDTU Termiz filiali mark platformasi</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js + Collapse plugin -->
    <script src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <!-- JSZip for client-side file compression -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    @stack('styles')
</head>
<body class="font-sans antialiased">
<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    @include('layouts.student-navigation')

    <!-- Page Heading -->
    @if (isset($header))
        <header class="bg-white dark:bg-gray-800 shadow" style="margin-bottom:15px;">
            {{-- Desktop: oddiy header --}}
            <div class="hidden sm:block max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
            {{-- Mobile: back button + centered title --}}
            <div class="sm:hidden flex items-center justify-between px-4 py-2 relative" style="min-height:44px;">
                <button onclick="window.history.back()" class="flex items-center justify-center rounded-full hover:bg-gray-100 transition" style="width:32px;height:32px;z-index:1;">
                    <svg class="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 text-center">{{ $header }}</div>
                </div>
                <div style="width:32px;"></div>
            </div>
        </header>
    @endif

    {{-- Telegram tasdiqlash ogohlantirishi --}}
    @auth('student')
        @php
            $authStudent = auth()->guard('student')->user();
        @endphp
        @if($authStudent && $authStudent->phone && !$authStudent->isTelegramVerified())
            @php
                $daysLeft = $authStudent->telegramDaysLeft();
            @endphp
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
                <div class="flex items-center justify-between px-4 py-3 rounded-lg border
                    {{ $daysLeft <= 2 ? 'bg-red-50 border-red-200' : ($daysLeft <= 4 ? 'bg-yellow-50 border-yellow-200' : 'bg-blue-50 border-blue-200') }}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0 {{ $daysLeft <= 2 ? 'text-red-500' : ($daysLeft <= 4 ? 'text-yellow-500' : 'text-blue-500') }}" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium {{ $daysLeft <= 2 ? 'text-red-800' : ($daysLeft <= 4 ? 'text-yellow-800' : 'text-blue-800') }}">
                                {{ __('Telegram hisobingizni tasdiqlang!') }}
                                @if($daysLeft > 0)
                                    <span class="font-bold">{{ $daysLeft }} {{ __('kun') }}</span> {{ __('qoldi.') }}
                                @else
                                    <span class="font-bold">{{ __('Muhlat tugadi!') }}</span>
                                @endif
                            </p>
                            <p class="text-xs {{ $daysLeft <= 2 ? 'text-red-600' : ($daysLeft <= 4 ? 'text-yellow-600' : 'text-blue-600') }}">
                                @if($daysLeft <= 0)
                                    {{ __('Telegram tasdiqlanmaguncha tizimdan foydalanish cheklanadi.') }}
                                @else
                                    {{ __('Muhlat tugagandan so\'ng tizimga kirish cheklanadi.') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('student.complete-profile') }}"
                       class="ml-3 inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition flex-shrink-0
                       {{ $daysLeft <= 2 ? 'bg-red-600 text-white hover:bg-red-700' : ($daysLeft <= 4 ? 'bg-yellow-600 text-white hover:bg-yellow-700' : 'bg-blue-600 text-white hover:bg-blue-700') }}">
                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                        {{ __('Tasdiqlash') }}
                    </a>
                </div>
            </div>
        @endif
    @endauth

    {{-- Xalqaro talaba ogohlantirish tizimi --}}
    @auth('student')
        @php
            $vs = auth()->guard('student')->user();
            $isXd = $vs && (str_starts_with(strtolower($vs->group_name ?? ''), 'xd') || ($vs->citizenship_code && $vs->citizenship_code !== '' && $vs->citizenship_code !== 'UZ'));
            $vi = $isXd ? \App\Models\StudentVisaInfo::where('student_id', $vs->id)->first() : null;

            $blockSite = false;      // Saytni to'liq bloklash
            $showPassportModal = false; // Pasport topshirish modali
            $showFillModal = false;  // Ma'lumot to'ldirish modali (yopiladigan)
            $topBanner = null;       // Tepa banner

            if ($isXd) {
                $onVisaPage = request()->routeIs('student.visa-info.*');

                if (!$vi) {
                    // Ma'lumotlar umuman kiritilmagan
                    $showFillModal = !$onVisaPage;
                    $topBanner = ['level' => 'warning', 'msg' => 'Viza ma\'lumotlaringizni to\'ldiring!'];
                } elseif ($vi) {
                    $rDays = $vi->registrationDaysLeft();
                    $vDays = $vi->visaDaysLeft();
                    $regActive = $vi->isRegistrationProcessActive();
                    $visActive = $vi->isVisaProcessActive();
                    $anyProcessActive = $regActive || $visActive;
                    $regDone = ($vi->registration_process_status ?? 'none') === 'done';
                    $visDone = ($vi->visa_process_status ?? 'none') === 'done';

                    // 1. Jarayon davom etmoqda — ogohlantirish o'rniga info xabar
                    if ($anyProcessActive) {
                        $processParts = [];
                        if ($visActive) $processParts[] = 'Vizangiz yangilanmoqda';
                        elseif ($regActive) $processParts[] = 'Registratsiyangiz yangilanmoqda';
                        $topBanner = ['level' => 'info', 'msg' => implode('. ', $processParts) . '. Jarayon tugashini kuting.'];
                    }
                    // 2. Jarayon tugallandi, pasport qaytarildi — ma'lumotlarni qayta to'ldirish kerak
                    elseif ($regDone || $visDone) {
                        $needFill = [];
                        if ($regDone && !$vi->registration_end_date) $needFill[] = 'registratsiya';
                        if ($visDone && !$vi->visa_end_date) $needFill[] = 'viza';
                        if (count($needFill) > 0) {
                            // Muddat tekshirish — 3 kun o'tgan bo'lsa bloklash
                            $deadline = $vi->visa_info_deadline;
                            $deadlinePassed = $deadline && now()->greaterThan($deadline);
                            if ($deadlinePassed) {
                                $blockSite = !$onVisaPage;
                                $topBanner = ['level' => 'danger', 'msg' => 'Yangi ' . implode(' va ', $needFill) . ' ma\'lumotlaringizni kiritish muddati tugadi! Iltimos, zudlik bilan kiriting.'];
                            } else {
                                $showFillModal = !$onVisaPage;
                                $daysToDeadline = $deadline ? (int) now()->startOfDay()->diffInDays($deadline, false) : null;
                                $daysText = $daysToDeadline !== null ? " ({$daysToDeadline} kun qoldi)" : '';
                                $topBanner = ['level' => 'warning', 'msg' => 'Yangi ' . implode(' va ', $needFill) . " ma'lumotlaringizni kiriting!{$daysText}"];
                            }
                        }
                    }
                    // 3. Muddati tugagan — bloklash
                    elseif (($rDays !== null && $rDays <= 0) || ($vDays !== null && $vDays <= 0)) {
                        $blockSite = !$onVisaPage;
                        $expired = [];
                        if ($rDays !== null && $rDays <= 0) $expired[] = 'Registratsiya muddati tugagan!';
                        if ($vDays !== null && $vDays <= 0) $expired[] = 'Viza muddati tugagan!';
                        $topBanner = ['level' => 'danger', 'msg' => implode(' ', $expired) . ' Registrator ofisiga murojaat qiling.'];
                    }
                    // 4. Registratsiya 3 kun / Viza 15 kun — bloklash
                    elseif (($rDays !== null && $rDays <= 3 && !$vi->passport_handed_over) || ($vDays !== null && $vDays <= 15 && !$vi->passport_handed_over)) {
                        $blockSite = !$onVisaPage;
                        $msg = ($vDays !== null && $vDays <= 15) ? "Viza muddati tugashiga {$vDays} kun!" : "Registratsiya muddati tugashiga {$rDays} kun!";
                        $topBanner = ['level' => 'danger', 'msg' => $msg . ' Pasportingizni topshiring!'];
                    }
                    // 5. Registratsiya 5 kun / Viza 20 kun — pasport topshirish modali
                    elseif (($rDays !== null && $rDays <= 5 && !$vi->passport_handed_over) || ($vDays !== null && $vDays <= 20 && !$vi->passport_handed_over)) {
                        $showPassportModal = !$onVisaPage;
                        $msg = ($vDays !== null && $vDays <= 20) ? "Viza tugashiga {$vDays} kun" : "Registratsiya tugashiga {$rDays} kun";
                        $topBanner = ['level' => 'warning', 'msg' => $msg . '. Pasportingizni firmaga yoki registrator ofisiga topshiring.'];
                    }

                    // 6. Yashil/sariq ogohlantirish (hali bloklash emas)
                    if (!$topBanner) {
                        $parts = [];
                        if ($rDays !== null && $rDays <= 7) $parts[] = "Registratsiya tugashiga {$rDays} kun";
                        if ($vDays !== null && $vDays <= 30) $parts[] = "Viza tugashiga {$vDays} kun";
                        if (count($parts) > 0) {
                            $isDanger = ($rDays !== null && $rDays <= 3) || ($vDays !== null && $vDays <= 15);
                            $isWarn = ($rDays !== null && $rDays <= 5) || ($vDays !== null && $vDays <= 20);
                            $topBanner = ['level' => $isDanger ? 'danger' : ($isWarn ? 'warning' : 'info'), 'msg' => implode(' | ', $parts)];
                        }
                    }
                }
            }
        @endphp

        {{-- Tepa banner --}}
        @if($topBanner)
            @if($topBanner['level'] === 'danger')
                <div style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:10px 0;position:sticky;top:0;z-index:9990;">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 flex-shrink-0 animate-pulse" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            <span class="text-sm font-bold">{{ $topBanner['msg'] }}</span>
                        </div>
                        <a href="{{ route('student.visa-info.index') }}" class="px-3 py-1 bg-white text-red-700 text-xs font-bold rounded hover:bg-red-50 transition flex-shrink-0">Viza ma'lumotlarim</a>
                    </div>
                </div>
            @elseif($topBanner['level'] === 'warning')
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
                    <div class="flex items-center justify-between px-4 py-3 rounded-lg border bg-yellow-50 border-yellow-200">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-yellow-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            <span class="text-sm font-semibold text-yellow-800">{{ $topBanner['msg'] }}</span>
                        </div>
                        <a href="{{ route('student.visa-info.index') }}" class="px-3 py-1 bg-yellow-600 text-white text-xs font-bold rounded hover:bg-yellow-700 transition flex-shrink-0">Viza ma'lumotlarim</a>
                    </div>
                </div>
            @else
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
                    <div class="flex items-center justify-between px-4 py-3 rounded-lg border bg-green-50 border-green-200">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            <span class="text-sm font-medium text-green-800">{{ $topBanner['msg'] }}</span>
                        </div>
                        <a href="{{ route('student.visa-info.index') }}" class="px-3 py-1 bg-green-600 text-white text-xs font-bold rounded hover:bg-green-700 transition flex-shrink-0">Viza ma'lumotlarim</a>
                    </div>
                </div>
            @endif
        @endif

        {{-- Ma'lumot to'ldirish modali (yopiladigan) --}}
        @if($showFillModal)
            <div x-data="{ show: true }" x-show="show" style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);">
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6 text-center">
                    <svg class="w-16 h-16 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Viza ma'lumotlaringizni to'ldiring!</h3>
                    <p class="text-sm text-gray-600 mb-6">Platformadan to'liq foydalanish uchun viza, registratsiya va pasport ma'lumotlaringizni kiritishingiz kerak.</p>
                    <div class="flex gap-3">
                        <button @click="show = false" class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Keyinroq</button>
                        <a href="{{ route('student.visa-info.index') }}" class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition text-center">To'ldirish</a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Pasport topshirish modali (yopiladigan) --}}
        @if($showPassportModal)
            <div x-data="{ show: true }" x-show="show" style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);">
                <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6 text-center">
                    <svg class="w-16 h-16 text-orange-500 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/></svg>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Pasportingizni topshiring!</h3>
                    <p class="text-sm text-gray-600 mb-6">Pasportingizni o'zingizning firmaga yoki registrator ofisiga topshiring. Muddati yaqinlashmoqda!</p>
                    <button @click="show = false" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700 transition">Tushundim</button>
                </div>
            </div>
        @endif

        {{-- Saytni bloklash modali (YOPILMAYDI) --}}
        @if($blockSite)
            <div style="position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.7);">
                <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 p-8 text-center">
                    <div style="width:80px;height:80px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                        <svg class="w-10 h-10 text-red-600 animate-pulse" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-red-700 mb-3">Platformadan foydalanish cheklangan!</h3>
                    <p class="text-sm text-gray-600 mb-2">{{ $topBanner['msg'] ?? '' }}</p>
                    <p class="text-sm text-gray-600 mb-6">Pasportingizni registrator ofisiga yoki firmangizga topshiring. Platformadan foydalanish uchun registratsiya jarayoni boshlanishi kerak.</p>
                    <a href="{{ route('student.visa-info.index') }}" class="inline-block px-6 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Viza ma'lumotlarim</a>
                </div>
            </div>
        @endif
    @endauth

    {{-- Impersonatsiya banneri --}}
    @if(session('impersonating'))
        <div class="bg-red-600 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between">
                <span class="text-sm font-medium">
                    {{ __('Siz hozir') }} <strong>{{ session('impersonated_name') }}</strong> {{ __('sifatida kirgansiz (Superadmin rejimi)') }}
                </span>
                <form action="{{ route('impersonate.stop') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="ml-4 px-3 py-1 bg-white text-red-600 text-xs font-bold rounded hover:bg-red-50 transition">
                        {{ __('Orqaga qaytish') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Page Content -->
    <main class="pb-20 sm:pb-0">
        {{ $slot }}
    </main>
</div>

    <!-- Mobile Bottom Navigation Bar -->
    @php
        $activeTab = 'none';
        if (request()->routeIs('student.subjects') || request()->routeIs('student.subject.*')) $activeTab = 'fanlar';
        elseif (request()->routeIs('student.schedule')) $activeTab = 'jadval';
        elseif (request()->routeIs('student.dashboard')) $activeTab = 'asosiy';
        elseif (request()->routeIs('student.independents')) $activeTab = 'mt';
        elseif (request()->routeIs('student.exam-schedule') || request()->routeIs('student.services') || request()->routeIs('student.absence-excuses.*') || request()->routeIs('student.contracts.*') || request()->routeIs('student.attendance') || request()->routeIs('student.pending-lessons') || request()->routeIs('student.visa-info.*')) $activeTab = 'foydali';
    @endphp
    <div x-data="{ boshqalarOpen: false }" class="sm:hidden" style="position:fixed !important;bottom:0 !important;left:0 !important;right:0 !important;z-index:9999 !important;">
        <!-- Boshqalar popup overlay -->
        <div x-show="boshqalarOpen" @click="boshqalarOpen = false" style="position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,0.3);display:none;"></div>

        <!-- Boshqalar popup menu -->
        <div x-show="boshqalarOpen" @click.away="boshqalarOpen = false" style="position:absolute;bottom:100%;margin-bottom:0.5rem;left:1rem;right:1rem;z-index:9999;display:none;background-color:#ffffff;" class="mx-auto max-w-sm rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-3">
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('student.exam-schedule') }}" class="flex items-center rounded-xl border border-gray-200 transition {{ request()->routeIs('student.exam-schedule') ? 'bg-indigo-50 border-indigo-300' : 'bg-white hover:bg-gray-50' }}" style="padding:10px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div class="rounded-xl bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center flex-shrink-0" style="width:50px;height:50px;">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                    <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-300 leading-tight ml-3">{{ __('Imtihon jadvali') }}</span>
                </a>
                <a href="{{ route('student.services') }}" class="flex items-center rounded-xl border border-gray-200 transition {{ request()->routeIs('student.services') || request()->routeIs('student.absence-excuses.*') || request()->routeIs('student.clubs*') ? 'bg-indigo-50 border-indigo-300' : 'bg-white hover:bg-gray-50' }}" style="padding:10px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div class="rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0" style="width:50px;height:50px;">
                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </div>
                    <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-300 leading-tight ml-3">{{ __('Xizmatlar') }}</span>
                </a>
                <a href="{{ route('student.attendance') }}" class="flex items-center rounded-xl border border-gray-200 transition {{ request()->routeIs('student.attendance') ? 'bg-indigo-50 border-indigo-300' : 'bg-white hover:bg-gray-50' }}" style="padding:10px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div class="rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center flex-shrink-0" style="width:50px;height:50px;">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-300 leading-tight ml-3">{{ __('Davomat') }}</span>
                </a>
                <a href="{{ route('student.pending-lessons') }}" class="flex items-center rounded-xl border border-gray-200 transition {{ request()->routeIs('student.pending-lessons') ? 'bg-indigo-50 border-indigo-300' : 'bg-white hover:bg-gray-50' }}" style="padding:10px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div class="rounded-xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center flex-shrink-0" style="width:50px;height:50px;">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" />
                        </svg>
                    </div>
                    <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-300 leading-tight ml-3">{{ __('Qayta topshirish') }}</span>
                </a>
                @php $mobileStudent = auth()->guard('student')->user(); @endphp
                @if($mobileStudent && (str_starts_with(strtolower($mobileStudent->group_name ?? ''), 'xd') || ($mobileStudent->citizenship_code && $mobileStudent->citizenship_code !== '' && $mobileStudent->citizenship_code !== 'UZ')))
                <a href="{{ route('student.visa-info.index') }}" class="flex items-center rounded-xl border border-gray-200 transition {{ request()->routeIs('student.visa-info.*') ? 'bg-indigo-50 border-indigo-300' : 'bg-white hover:bg-gray-50' }}" style="padding:10px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <div class="rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center flex-shrink-0" style="width:50px;height:50px;">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                    </div>
                    <span class="flex-1 text-sm font-semibold text-gray-700 dark:text-gray-300 leading-tight ml-3">{{ __('Viza ma\'lumotlarim') }}</span>
                </a>
                @endif
            </div>
        </div>

        <!-- Bottom Navigation Tabs -->
        <div class="flex items-center justify-between" style="background-color:#0f3487;height:60px;padding:0 15px;padding-bottom:max(5px, env(safe-area-inset-bottom));margin:0 10px 10px 10px;border-radius:10px;">
            <!-- 1. Fanlar -->
            <a href="{{ route('student.subjects') }}" class="flex flex-col items-center justify-center" style="width:55px;gap:3px;">
                @if($activeTab === 'fanlar')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:44px;height:44px;">
                        <svg class="w-6 h-6" fill="none" stroke="#0f3487" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                @else
                    <svg class="w-6 h-6" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    <span class="text-[11px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">{{ __('Fanlar') }}</span>
                @endif
            </a>

            <!-- 2. Dars jadvali -->
            <a href="{{ route('student.schedule') }}" class="flex flex-col items-center justify-center" style="width:55px;gap:3px;">
                @if($activeTab === 'jadval')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:44px;height:44px;">
                        <svg class="w-6 h-6" fill="none" stroke="#0f3487" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                @else
                    <svg class="w-6 h-6" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <span class="text-[11px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">{{ __('Jadval') }}</span>
                @endif
            </a>

            <!-- 3. Asosiy -->
            <a href="{{ route('student.dashboard') }}" class="flex flex-col items-center justify-center" style="width:55px;gap:3px;">
                @if($activeTab === 'asosiy')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:44px;height:44px;">
                        <svg class="w-6 h-6" fill="none" stroke="#0f3487" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </div>
                @else
                    <svg class="w-6 h-6" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                    <span class="text-[11px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">{{ __('Asosiy') }}</span>
                @endif
            </a>

            <!-- 4. MT -->
            <a href="{{ route('student.independents') }}" class="flex flex-col items-center justify-center" style="width:55px;gap:3px;">
                @if($activeTab === 'mt')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:44px;height:44px;">
                        <svg class="w-6 h-6" fill="none" stroke="#0f3487" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                        </svg>
                    </div>
                @else
                    <svg class="w-6 h-6" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                    </svg>
                    <span class="text-[11px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">{{ __('MT') }}</span>
                @endif
            </a>

            <!-- 5. Boshqalar (radial fan trigger) -->
            <button @click="boshqalarOpen = !boshqalarOpen" class="flex flex-col items-center justify-center" style="width:55px;gap:3px;background:none;border:none;">
                @if($activeTab === 'foydali')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:44px;height:44px;">
                        <svg class="w-6 h-6" fill="none" stroke="#0f3487" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                    </div>
                @else
                    <svg class="w-6 h-6 transition-transform duration-300" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24"
                         style="opacity:0.7;" :style="boshqalarOpen ? 'transform:rotate(45deg);opacity:1' : ''">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                    </svg>
                    <span class="text-[11px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">{{ __('Boshqa') }}</span>
                @endif
            </button>
        </div>
    </div>

    @stack('scripts')

    @if(config('app.debug'))
    {{-- DEBUG: Console log - faqat debug rejimda --}}
    <script>
        console.group('%c LMS DEBUG: Student Layout', 'color: #3498db; font-weight: bold;');
        console.log('URL:', window.location.href);
        console.groupEnd();
    </script>
    @endif
</body>
</html>
