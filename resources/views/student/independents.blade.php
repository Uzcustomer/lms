<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Mustaqil ta\'lim topshiriqlari') }}
        </h2>
    </x-slot>

    <div class="pb-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if (session('success'))
                        <div id="flash-message" class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded font-semibold">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div id="flash-message" class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded font-semibold">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div id="flash-message" class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded font-semibold">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Compression overlay --}}
                    <div id="compress-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                        <div style="background:white; border-radius:12px; padding:24px 32px; text-align:center; max-width:360px;">
                            <div style="margin-bottom:12px;">
                                <svg class="animate-spin inline h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                            <p id="compress-status" style="font-size:14px; color:#1e40af; font-weight:600;">Fayl siqilmoqda...</p>
                            <p id="compress-detail" style="font-size:12px; color:#6b7280; margin-top:4px;"></p>
                        </div>
                    </div>

                    @if($independents->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">O'qituvchi</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Berilgan sana</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Muddat</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Holati</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Baho</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fayl yuklash</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($independents as $index => $item)
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                {{ $item['subject_name'] }}
                                                @if($item['file_path'])
                                                    <a href="{{ asset('storage/' . $item['file_path']) }}" target="_blank"
                                                       class="ml-1 text-blue-500 hover:text-blue-700" title="Topshiriq faylini ko'rish">
                                                        <svg class="inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                    </a>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $item['teacher_name'] }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $item['start_date'] }}</td>
                                            <td class="px-4 py-3 text-sm {{ $item['is_overdue'] ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                                {{ $item['deadline'] }}
                                                <span class="text-xs block">{{ $mtDeadlineTime ?? '17:00' }} gacha</span>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                @if($item['grade_locked'])
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        Qabul qilindi
                                                    </span>
                                                @elseif($item['submission'] && $item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60))
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                                        Qayta topshirish
                                                    </span>
                                                @elseif($item['submission'])
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        Yuklangan
                                                    </span>
                                                @elseif($item['is_overdue'])
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                        Muddat tugagan
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Kutilmoqda
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500">
                                                @if($item['grade'] !== null)
                                                    <span class="font-semibold {{ $item['grade'] >= ($minimumLimit ?? 60) ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ $item['grade'] }}
                                                    </span>
                                                    @if($item['grade_locked'])
                                                        <span class="text-xs text-green-500 block">Qulflangan</span>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                                @if($item['grade_history']->count() > 0)
                                                    <div class="mt-1 border-t border-gray-200 pt-1">
                                                        <span class="text-xs text-gray-400">Oldingi:</span>
                                                        @foreach($item['grade_history'] as $history)
                                                            <span class="text-xs {{ $history->grade >= ($minimumLimit ?? 60) ? 'text-green-500' : 'text-red-400' }}">
                                                                {{ $history->grade }}
                                                            </span>@if(!$loop->last), @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                @if($item['grade_locked'])
                                                    {{-- Grade >= minimumLimit: show file info only, no upload --}}
                                                    @if($item['submission'])
                                                        <a href="{{ asset('storage/' . $item['submission']->file_path) }}" target="_blank"
                                                           class="text-blue-500 hover:text-blue-700 text-xs underline">
                                                            {{ $item['submission']->file_original_name }}
                                                        </a>
                                                        <div class="text-xs text-gray-400 mt-1">
                                                            {{ $item['submission']->submitted_at->format('d.m.Y H:i') }}
                                                        </div>
                                                    @endif
                                                @elseif($item['yn_locked'])
                                                    {{-- YN ga yuborilgan: fayl yuklash bloklangan --}}
                                                    @if($item['submission'])
                                                        <a href="{{ asset('storage/' . $item['submission']->file_path) }}" target="_blank"
                                                           class="text-blue-500 hover:text-blue-700 text-xs underline">
                                                            {{ $item['submission']->file_original_name }}
                                                        </a>
                                                        <div class="text-xs text-gray-400 mt-1">
                                                            {{ $item['submission']->submitted_at->format('d.m.Y H:i') }}
                                                        </div>
                                                    @endif
                                                    <p class="text-xs text-orange-500 mt-1">YN ga yuborilgan — fayl yuklash mumkin emas</p>
                                                @elseif($item['submission'])
                                                    <div class="flex items-center space-x-2">
                                                        <a href="{{ asset('storage/' . $item['submission']->file_path) }}" target="_blank"
                                                           class="text-blue-500 hover:text-blue-700 text-xs underline">
                                                            {{ $item['submission']->file_original_name }}
                                                        </a>
                                                    </div>
                                                    <div class="text-xs text-gray-400 mt-1">
                                                        {{ $item['submission']->submitted_at->format('d.m.Y H:i') }}
                                                    </div>
                                                    @if($item['can_resubmit'])
                                                        <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                                                              enctype="multipart/form-data" class="mt-2 mt-upload-form">
                                                            @csrf
                                                            <label class="cursor-pointer text-xs text-orange-500 hover:text-orange-700 underline">
                                                                Qayta yuklash ({{ $item['remaining_attempts'] }} marta qoldi)
                                                                <input type="file" name="file" class="hidden mt-file-input"
                                                                       accept=".zip,.doc,.docx,.ppt,.pptx,.pdf">
                                                            </label>
                                                        </form>
                                                    @elseif($item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60) && $item['remaining_attempts'] <= 0)
                                                        <p class="text-xs text-red-400 mt-1">MT topshirig'ini qayta yuklash imkoniyati tugagan</p>
                                                    @elseif(!$item['is_overdue'] && $item['grade'] === null)
                                                        <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                                                              enctype="multipart/form-data" class="inline mt-2 mt-upload-form">
                                                            @csrf
                                                            <label class="cursor-pointer text-xs text-orange-500 hover:text-orange-700 underline">
                                                                Qayta yuklash
                                                                <input type="file" name="file" class="hidden mt-file-input"
                                                                       accept=".zip,.doc,.docx,.ppt,.pptx,.pdf">
                                                            </label>
                                                        </form>
                                                    @endif
                                                @elseif(!$item['is_overdue'])
                                                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                                                          enctype="multipart/form-data" class="flex items-center space-x-2 mt-upload-form">
                                                        @csrf
                                                        <input type="file" name="file" required
                                                               accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                                               class="text-xs w-40 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 mt-file-input">
                                                        <button type="submit"
                                                                class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600">
                                                            Yuklash
                                                        </button>
                                                    </form>
                                                    <p class="text-xs text-gray-400 mt-1">Max 10MB (zip, doc, ppt, pdf) — katta fayllar avtomatik siqiladi</p>
                                                @else
                                                    <span class="text-xs text-red-400">Muddat tugagan</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">Hozircha mustaqil ta'lim topshiriqlari mavjud emas.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xato yoki muvaffaqiyat xabari bo'lsa, sahifani tepaga scroll qilish
        var flashMessage = document.getElementById('flash-message');
        if (flashMessage) {
            flashMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        var COMPRESS_THRESHOLD = 2 * 1024 * 1024; // 2MB dan katta fayllarni siqish
        var MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB maksimal hajm

        document.querySelectorAll('.mt-upload-form').forEach(function(form) {
            var fileInput = form.querySelector('.mt-file-input');
            if (!fileInput) return;

            fileInput.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;

                // Fayl hajmi limitdan kichik bo'lsa, siqmasdan yuklash
                if (file.size <= COMPRESS_THRESHOLD) {
                    // Hidden input formda bo'lsa submit, button bo'lsa ham submit
                    if (fileInput.classList.contains('hidden')) {
                        form.submit();
                    }
                    return;
                }

                // ZIP faylni qayta siqish shart emas
                var ext = file.name.split('.').pop().toLowerCase();
                if (ext === 'zip') {
                    if (file.size > MAX_FILE_SIZE) {
                        alert('Fayl hajmi ' + (file.size / 1024 / 1024).toFixed(1) + 'MB. Maksimal hajm 10MB.');
                        fileInput.value = '';
                        return;
                    }
                    if (fileInput.classList.contains('hidden')) {
                        form.submit();
                    }
                    return;
                }

                // JSZip mavjudligini tekshirish
                if (typeof JSZip === 'undefined') {
                    alert('Siqish kutubxonasi yuklanmadi. Sahifani yangilang yoki faylni ZIP formatida yuklang.');
                    fileInput.value = '';
                    return;
                }

                // Overlay ko'rsatish
                var overlay = document.getElementById('compress-overlay');
                var statusEl = document.getElementById('compress-status');
                var detailEl = document.getElementById('compress-detail');
                overlay.style.display = 'flex';
                statusEl.textContent = 'Fayl siqilmoqda...';
                var originalSizeMB = (file.size / 1024 / 1024).toFixed(1);
                detailEl.textContent = 'Asl hajm: ' + originalSizeMB + 'MB';

                var zip = new JSZip();
                zip.file(file.name, file);

                zip.generateAsync({
                    type: 'blob',
                    compression: 'DEFLATE',
                    compressionOptions: { level: 6 }
                }, function(metadata) {
                    detailEl.textContent = 'Siqilmoqda... ' + metadata.percent.toFixed(0) + '%';
                }).then(function(blob) {
                    var compressedSizeMB = (blob.size / 1024 / 1024).toFixed(1);

                    if (blob.size > MAX_FILE_SIZE) {
                        overlay.style.display = 'none';
                        alert('Siqilgandan keyin ham fayl hajmi ' + compressedSizeMB + 'MB (' + originalSizeMB + 'MB dan). Maksimal hajm 10MB. Iltimos, faylni kichikroq qiling.');
                        fileInput.value = '';
                        return;
                    }

                    statusEl.textContent = 'Yuklanmoqda...';
                    var savedPercent = ((1 - blob.size / file.size) * 100).toFixed(0);
                    detailEl.textContent = originalSizeMB + 'MB → ' + compressedSizeMB + 'MB (' + savedPercent + '% siqildi)';

                    // Yangi ZIP faylni formga qo'shish
                    var zipFileName = file.name.replace(/\.[^.]+$/, '') + '.zip';
                    var zipFile = new File([blob], zipFileName, { type: 'application/zip' });

                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(zipFile);
                    fileInput.files = dataTransfer.files;

                    form.submit();
                }).catch(function(err) {
                    overlay.style.display = 'none';
                    alert('Faylni siqishda xatolik: ' + err.message);
                    fileInput.value = '';
                });
            });

            // Form submit bo'lganda overlay yashirishni oldini olish
            form.addEventListener('submit', function() {
                var overlay = document.getElementById('compress-overlay');
                if (overlay.style.display === 'flex') {
                    var statusEl = document.getElementById('compress-status');
                    statusEl.textContent = 'Yuklanmoqda...';
                }
            });
        });
    });
    </script>
</x-student-app-layout>
