<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Pasport ma'lumotlari
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3 pb-6">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('student.partials.passport-card')
    </div>

<script>
function checkFileSize(input) {
    var errorEl = input.parentElement.querySelector('[data-file-error]');
    if (input.files.length > 0 && input.files[0].size > 1024 * 1024) {
        errorEl.textContent = 'Fayl hajmi 1MB dan oshmasligi kerak!';
        errorEl.classList.remove('hidden');
        input.value = '';
    } else {
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }
}
</script>
</x-student-app-layout>
