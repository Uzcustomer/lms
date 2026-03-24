<div class="bg-white shadow rounded-lg p-5 mb-6 border border-gray-200" x-data="{ showPassportForm: {{ ($errors->any() || (session('success') && str_contains(session('success'), 'Fayl'))) ? 'true' : 'false' }} }">
    {{-- Header: title left, status badge top-right --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
            </svg>
            <h4 class="text-lg font-semibold text-gray-800">Pasport ma'lumotlarim</h4>
        </div>
        @if($studentPassport)
            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 border border-green-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
                To'ldirilgan
            </span>
        @else
            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 border border-red-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                To'ldirilmagan
            </span>
        @endif
    </div>

    {{-- Form (shown when toggled) --}}
    <div x-show="showPassportForm" x-transition class="border-t border-gray-200 pt-5">
        {{-- Warning card --}}
        <div class="mb-5 p-4 bg-yellow-50 border border-yellow-300 rounded-lg flex items-start gap-3">
            <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <p class="text-sm text-yellow-800 font-medium">Ma'lumotlaringizni pasport ma'lumotlaringiz bilan solishtiring, agar noto'g'ri bo'lsa o'zgartiring!</p>
        </div>

        @if($errors->any())
            <div class="mb-5 p-4 bg-red-50 border border-red-300 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <span class="text-sm font-semibold text-red-700">Iltimos, quyidagi xatoliklarni tuzating:</span>
                </div>
                <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.passport.store') }}" enctype="multipart/form-data" id="passportForm">
            @csrf
            {{-- O'zbekcha ism, familiya, otasining ismi --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Familiya <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name', $studentPassport->last_name ?? $student->second_name ?? '') }}" required
                           class="w-full rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase {{ $errors->has('last_name') ? 'border-red-500' : 'border-gray-300' }}"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('last_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ism <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $studentPassport->first_name ?? $student->first_name ?? '') }}" required
                           class="w-full rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase {{ $errors->has('first_name') ? 'border-red-500' : 'border-gray-300' }}"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Otasining ismi <span class="text-red-500">*</span></label>
                    <input type="text" name="father_name" value="{{ old('father_name', $studentPassport->father_name ?? $student->third_name ?? '') }}" required
                           class="w-full rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase {{ $errors->has('father_name') ? 'border-red-500' : 'border-gray-300' }}"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('father_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Inglizcha familiya va ism --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Inglizcha familiya <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name_en" value="{{ old('last_name_en', $studentPassport->last_name_en ?? '') }}" required
                           class="w-full rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase {{ $errors->has('last_name_en') ? 'border-red-500' : 'border-gray-300' }}"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('last_name_en') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Inglizcha ism <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name_en" value="{{ old('first_name_en', $studentPassport->first_name_en ?? '') }}" required
                           class="w-full rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase {{ $errors->has('first_name_en') ? 'border-red-500' : 'border-gray-300' }}"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('first_name_en') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passport seriya va raqamingiz <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" name="passport_series" value="{{ old('passport_series', $studentPassport->passport_series ?? '') }}" required
                               class="w-24 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase tracking-widest font-semibold text-center {{ $errors->has('passport_series') ? 'border-red-500' : 'border-gray-300' }}"
                               placeholder="AA" maxlength="2"
                               oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase()">
                        <input type="text" name="passport_number" value="{{ old('passport_number', $studentPassport->passport_number ?? '') }}" required
                               class="flex-1 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 tracking-wide {{ $errors->has('passport_number') ? 'border-red-500' : 'border-gray-300' }}"
                               placeholder="1234567" maxlength="7"
                               oninput="this.value = this.value.replace(/\D/g, '')">
                    </div>
                    @error('passport_series') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    @error('passport_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">JSHSHIR (JShShIR) <span class="text-red-500">*</span></label>
                    <input type="text" name="jshshir" value="{{ old('jshshir', $studentPassport->jshshir ?? '') }}" required
                           class="w-full rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 tracking-wide {{ $errors->has('jshshir') ? 'border-red-500' : 'border-gray-300' }}"
                           placeholder="12345678901234" maxlength="14"
                           oninput="this.value = this.value.replace(/\D/g, '')">
                    @error('jshshir') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Pasport oldi tarafi --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pasport oldi tarafi <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="file" name="passport_front" id="file_passport_front" accept=".jpg,.jpeg,.pdf"
                               onchange="checkFileSize(this); previewFile(this, 'preview-new-passport_front')"
                               class="hidden" {{ !$studentPassport?->passport_front_path ? 'data-file-required' : '' }}>
                        <label for="file_passport_front" class="flex items-center gap-2 cursor-pointer w-full px-4 py-2 rounded-lg border text-sm text-gray-500 hover:bg-gray-50 {{ $errors->has('passport_front') ? 'border-red-500' : 'border-gray-300' }}">
                            <span class="inline-flex items-center px-4 py-1.5 bg-indigo-50 text-indigo-700 font-semibold rounded-lg text-sm">Fayl tanlang</span>
                            <span id="label_passport_front" class="truncate">Fayl yuklanmagan</span>
                        </label>
                    </div>
                    @error('passport_front') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                    <div id="preview-new-passport_front" class="mt-2 hidden"></div>
                    @if($studentPassport?->passport_front_path)
                        <div class="mt-2 border border-gray-200 rounded-lg bg-gray-50 relative overflow-auto" id="preview-passport_front_path" style="height:170px">
                            <button type="button" onclick="deletePassportFile('passport_front_path')" class="absolute top-2 right-2 z-20 bg-red-500 hover:bg-red-600 text-white rounded-full p-1.5 shadow-lg transition" title="O'chirish">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            @if(Str::endsWith($studentPassport->passport_front_path, ['.jpg', '.jpeg', '.png']))
                                <img src="{{ route('student.passport.file', 'passport_front_path') }}" alt="Pasport oldi" style="width:100%;height:100%;object-fit:contain" class="rounded-lg">
                            @else
                                <iframe src="{{ route('student.passport.file', 'passport_front_path') }}" style="width:100%;height:100%" class="border-0 rounded-lg"></iframe>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Pasport orqa tarafi --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pasport orqa tarafi <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="file" name="passport_back" id="file_passport_back" accept=".jpg,.jpeg,.pdf"
                               onchange="checkFileSize(this); previewFile(this, 'preview-new-passport_back')"
                               class="hidden" {{ !$studentPassport?->passport_back_path ? 'data-file-required' : '' }}>
                        <label for="file_passport_back" class="flex items-center gap-2 cursor-pointer w-full px-4 py-2 rounded-lg border text-sm text-gray-500 hover:bg-gray-50 {{ $errors->has('passport_back') ? 'border-red-500' : 'border-gray-300' }}">
                            <span class="inline-flex items-center px-4 py-1.5 bg-indigo-50 text-indigo-700 font-semibold rounded-lg text-sm">Fayl tanlang</span>
                            <span id="label_passport_back" class="truncate">Fayl yuklanmagan</span>
                        </label>
                    </div>
                    @error('passport_back') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                    <div id="preview-new-passport_back" class="mt-2 hidden"></div>
                    @if($studentPassport?->passport_back_path)
                        <div class="mt-2 border border-gray-200 rounded-lg bg-gray-50 relative overflow-auto" id="preview-passport_back_path" style="height:170px">
                            <button type="button" onclick="deletePassportFile('passport_back_path')" class="absolute top-2 right-2 z-20 bg-red-500 hover:bg-red-600 text-white rounded-full p-1.5 shadow-lg transition" title="O'chirish">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            @if(Str::endsWith($studentPassport->passport_back_path, ['.jpg', '.jpeg', '.png']))
                                <img src="{{ route('student.passport.file', 'passport_back_path') }}" alt="Pasport orqa" style="width:100%;height:100%;object-fit:contain" class="rounded-lg">
                            @else
                                <iframe src="{{ route('student.passport.file', 'passport_back_path') }}" style="width:100%;height:100%" class="border-0 rounded-lg"></iframe>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Xorijga chiqish pasporti --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Xorijga chiqish pasporti <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="file" name="foreign_passport" id="file_foreign_passport" accept=".jpg,.jpeg,.pdf"
                               onchange="checkFileSize(this); previewFile(this, 'preview-new-foreign_passport')"
                               class="hidden" {{ !$studentPassport?->foreign_passport_path ? 'data-file-required' : '' }}>
                        <label for="file_foreign_passport" class="flex items-center gap-2 cursor-pointer w-full px-4 py-2 rounded-lg border text-sm text-gray-500 hover:bg-gray-50 {{ $errors->has('foreign_passport') ? 'border-red-500' : 'border-gray-300' }}">
                            <span class="inline-flex items-center px-4 py-1.5 bg-indigo-50 text-indigo-700 font-semibold rounded-lg text-sm">Fayl tanlang</span>
                            <span id="label_foreign_passport" class="truncate">Fayl yuklanmagan</span>
                        </label>
                    </div>
                    @error('foreign_passport') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                    <div id="preview-new-foreign_passport" class="mt-2 hidden"></div>
                    @if($studentPassport?->foreign_passport_path)
                        <div class="mt-2 border border-gray-200 rounded-lg bg-gray-50 relative overflow-auto" id="preview-foreign_passport_path" style="height:170px">
                            <button type="button" onclick="deletePassportFile('foreign_passport_path')" class="absolute top-2 right-2 z-20 bg-red-500 hover:bg-red-600 text-white rounded-full p-1.5 shadow-lg transition" title="O'chirish">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            @if(Str::endsWith($studentPassport->foreign_passport_path, ['.jpg', '.jpeg', '.png']))
                                <img src="{{ route('student.passport.file', 'foreign_passport_path') }}" alt="Xorijga chiqish pasporti" style="width:100%;height:100%;object-fit:contain" class="rounded-lg">
                            @else
                                <iframe src="{{ route('student.passport.file', 'foreign_passport_path') }}" style="width:100%;height:100%" class="border-0 rounded-lg"></iframe>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            <div class="text-right mt-4">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Saqlash
                </button>
            </div>
        </form>
    </div>

    {{-- Button at the bottom --}}
    <div class="mt-4" x-show="!showPassportForm">
        <button @click="showPassportForm = true" type="button"
                class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-lg transition {{ $studentPassport ? 'bg-yellow-500 text-white hover:bg-yellow-600' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
            {{ $studentPassport ? 'Tahrirlash' : "To'ldirish" }}
        </button>
    </div>
    <div class="mt-4" x-show="showPassportForm">
        <button @click="showPassportForm = false" type="button"
                class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-lg transition bg-gray-200 text-gray-700 hover:bg-gray-300">
            Yopish
        </button>
    </div>
</div>

<script>
var deletedFields = {};

var fileErrorMessages = {
    passport_front: 'Pasport oldi tarafini yuklang.',
    passport_back: 'Pasport orqa tarafini yuklang.',
    foreign_passport: 'Xorijga chiqish pasportini yuklang.'
};

function deletePassportFile(field) {
    if (!confirm("Faylni o'chirmoqchimisiz?")) return;

    fetch('/student/passport/file/' + field + '/delete', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
    }).then(function(response) {
        if (response.ok) {
            var preview = document.getElementById('preview-' + field);
            if (preview) preview.remove();

            var inputName = field.replace('_path', '');
            deletedFields[inputName] = true;
        } else {
            alert("Xatolik yuz berdi!");
        }
    });
}

function getFileLabelEl(inputName) {
    return document.querySelector('label[for="file_' + inputName + '"]');
}

function showFileError(inputName, message) {
    var labelEl = getFileLabelEl(inputName);
    if (labelEl) {
        labelEl.classList.remove('border-gray-300');
        labelEl.classList.add('border-red-500');
    }
    var errorEl = document.getElementById('error_' + inputName);
    if (!errorEl) {
        errorEl = document.createElement('p');
        errorEl.id = 'error_' + inputName;
        errorEl.className = 'text-xs text-red-600 mt-1';
        var container = document.getElementById('file_' + inputName).closest('.relative');
        container.parentNode.insertBefore(errorEl, container.nextSibling);
    }
    errorEl.textContent = message;
}

function clearFileError(inputName) {
    var labelEl = getFileLabelEl(inputName);
    if (labelEl) {
        labelEl.classList.remove('border-red-500');
        labelEl.classList.add('border-gray-300');
    }
    var errorEl = document.getElementById('error_' + inputName);
    if (errorEl) errorEl.remove();
}

function previewFile(input, previewId) {
    var previewContainer = document.getElementById(previewId);
    var fieldName = input.name;
    var labelEl = document.getElementById('label_' + fieldName);

    if (input.files.length === 0) {
        previewContainer.classList.add('hidden');
        previewContainer.innerHTML = '';
        if (labelEl) labelEl.textContent = 'Fayl yuklanmagan';
        return;
    }

    var file = input.files[0];
    if (labelEl) labelEl.textContent = file.name;

    clearFileError(fieldName);

    // Yangi fayl tanlaganda eski previewni yashirish
    var oldPreview = document.getElementById('preview-' + fieldName + '_path');
    if (oldPreview) oldPreview.style.display = 'none';

    var url = URL.createObjectURL(file);
    previewContainer.innerHTML = '';

    previewContainer.style.height = '170px';
    previewContainer.style.overflow = 'auto';
    previewContainer.className = 'mt-2 border border-gray-200 rounded-lg bg-gray-50';

    if (file.type.startsWith('image/')) {
        var img = document.createElement('img');
        img.src = url;
        img.alt = 'Tanlangan fayl';
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'contain';
        img.className = 'rounded-lg';
        previewContainer.appendChild(img);
    } else {
        var iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.className = 'border-0 rounded-lg';
        previewContainer.appendChild(iframe);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('passportForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        var hasError = false;
        var fileFields = ['passport_front', 'passport_back', 'foreign_passport'];

        fileFields.forEach(function(fieldName) {
            var input = document.getElementById('file_' + fieldName);
            if (!input) return;

            var existingPreview = document.getElementById('preview-' + fieldName + '_path');
            var hasExistingFile = existingPreview && existingPreview.style.display !== 'none';
            var hasNewFile = input.files && input.files.length > 0;
            var isRequired = input.hasAttribute('data-file-required') || deletedFields[fieldName];

            if (isRequired && !hasExistingFile && !hasNewFile) {
                showFileError(fieldName, fileErrorMessages[fieldName]);
                hasError = true;
            } else {
                clearFileError(fieldName);
            }
        });

        if (hasError) {
            e.preventDefault();
        }
    });
});
</script>
