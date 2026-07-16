<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Qabul ko'rsatkichi — yangi qo'shish</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.admission-indicators.store') }}">
                @include('admin.admission-indicators._form')
            </form>
        </div>
    </div>
</x-app-layout>
