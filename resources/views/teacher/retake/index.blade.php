<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish arizalari") }}
            <small class="text-muted text-sm font-normal">
                — {{ $role === 'dean' ? __('Dekan paneli') : __('Registrator paneli') }}
            </small>
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">

        {{-- Statistika kartlari --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                <p class="text-xs text-gray-500 uppercase">{{ __('Mening tasdiqimni kutyapti') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['pending'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <p class="text-xs text-gray-500 uppercase">{{ __('Tasdiqlangan') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['approved'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <p class="text-xs text-gray-500 uppercase">{{ __('Rad etilgan') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['rejected'] }}</p>
            </div>
        </div>

        {{-- Filtrlar --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
            <form method="GET" action="{{ route('admin.retake.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">{{ __('Qidirish') }}</label>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="{{ __('Talaba F.I.Sh. yoki HEMIS ID') }}"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('Holat') }}</label>
                    <select name="filter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                        <option value="pending_mine" {{ $filter === 'pending_mine' ? 'selected' : '' }}>{{ __('Tasdiqimni kutyapti') }}</option>
                        <option value="approved" {{ $filter === 'approved' ? 'selected' : '' }}>{{ __('Tasdiqlangan') }}</option>
                        <option value="rejected" {{ $filter === 'rejected' ? 'selected' : '' }}>{{ __('Rad etilgan') }}</option>
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>{{ __('Barchasi') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('Sanadan') }}</label>
                    <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('Sanagacha') }}</label>
                    <input type="date" name="date_to" value="{{ $dateTo ?? '' }}"
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                </div>
                <div class="md:col-span-5 flex gap-2">
                    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        {{ __('Filtrlash') }}
                    </button>
                    <a href="{{ route('admin.retake.index') }}" class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        {{ __('Tozalash') }}
                    </a>
                </div>
            </form>
        </div>

        {{-- Xabarlar --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Arizalar ro'yxati --}}
        @if($groups->count() === 0)
            <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                <p class="text-gray-500">{{ __('Tanlangan filtr bo\'yicha arizalar topilmadi') }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($groups as $group)
                    @include('teacher.retake._group_card', ['group' => $group, 'role' => $role, 'minReasonLength' => $minReasonLength])
                @endforeach
            </div>

            <div class="mt-4">
                {{ $groups->links() }}
            </div>
        @endif
    </div>
</x-teacher-app-layout>
