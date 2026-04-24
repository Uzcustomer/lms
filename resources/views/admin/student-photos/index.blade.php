<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Talaba rasmlari
        </h2>
    </x-slot>

    <div class="py-6" x-data="{
            lightbox: { open: false, src: '', alt: '' },
            compare: {
                open: false, profile: '', uploaded: '', title: '',
                photoId: null, loading: false, result: null, error: null,
            },
            reject: { open: false, id: null, name: '' },
            openLightbox(src, alt) { this.lightbox = { open: true, src, alt }; },
            openCompare(photoId, profile, uploaded, title, existing) {
                this.compare = {
                    open: true, profile, uploaded, title,
                    photoId, loading: false, result: existing || null, error: null,
                };
            },
            openReject(id, name) { this.reject = { open: true, id, name }; },
            async runSimilarityCheck() {
                this.compare.loading = true;
                this.compare.error = null;
                try {
                    const res = await fetch(`/admin/student-photos/${this.compare.photoId}/check-similarity`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.compare.error = data.error || 'Nomaʼlum xatolik';
                    } else {
                        this.compare.result = data;
                    }
                } catch (e) {
                    this.compare.error = e.message;
                } finally {
                    this.compare.loading = false;
                }
            },
        }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Statistika --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Jami rasmlar</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Kutilmoqda</div>
                    <div class="text-2xl font-semibold text-yellow-600">{{ number_format($stats['pending']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Tasdiqlangan</div>
                    <div class="text-2xl font-semibold text-green-600">{{ number_format($stats['approved']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Rad etilgan</div>
                    <div class="text-2xl font-semibold text-red-600">{{ number_format($stats['rejected']) }}</div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Filtrlar --}}
                    <form method="GET" action="{{ route('admin.student-photos.index') }}" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Qidiruv (FISH / ID)</label>
                                <input type="text" name="search" value="{{ request('search') }}"
                                       placeholder="Ism yoki talaba ID"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Holat</label>
                                <select name="status" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Tasdiqlangan</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                                <select name="department" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d }}" {{ request('department') == $d ? 'selected' : '' }}>{{ $d }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish</label>
                                <select name="specialty" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($specialties as $s)
                                        <option value="{{ $s }}" {{ request('specialty') == $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kurs</label>
                                <select name="level" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($levels as $l)
                                        <option value="{{ $l }}" {{ request('level') == $l ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Guruh</label>
                                <input type="text" name="group" value="{{ request('group') }}"
                                       placeholder="Guruh nomi"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tyutor</label>
                                <input type="text" name="tutor" value="{{ request('tutor') }}"
                                       placeholder="Tyutor ismi"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sana (dan)</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sana (gacha)</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                Filtrlash
                            </button>
                            <a href="{{ route('admin.student-photos.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200">
                                Tozalash
                            </a>
                        </div>
                    </form>

                    {{-- Jadval --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">#</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">FISH</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Talaba ID</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Fakultet</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Yo'nalish</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Kurs</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Guruh</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Rasm</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Tyutor</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Tekshirish natijasi</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Ruxsat</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($photos as $i => $photo)
                                    @php
                                        $groupName = $photo->student_group_name ?? $photo->group_name;
                                        $uploadedUrl = asset($photo->photo_path);
                                        $profileUrl = $photo->student_profile_image ?: null;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-500">{{ $photos->firstItem() + $i }}</td>
                                        <td class="px-3 py-2 font-medium text-gray-900">{{ $photo->full_name }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->student_id_number }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->department_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->specialty_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->level_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $groupName ?? '—' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <img src="{{ $uploadedUrl }}"
                                                 alt="{{ $photo->full_name }}"
                                                 class="inline-block w-12 h-16 object-cover rounded border border-gray-200 cursor-pointer hover:ring-2 hover:ring-blue-400"
                                                 @click="openLightbox('{{ $uploadedUrl }}', {{ Js::from($photo->full_name) }})"
                                                 loading="lazy">
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->uploaded_by }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @php
                                                $existingSimilarity = $photo->similarity_score !== null ? [
                                                    'similarity_percent' => (float) $photo->similarity_score,
                                                    'match' => $photo->similarity_status === 'match',
                                                    'status' => $photo->similarity_status,
                                                    'checked_at' => optional($photo->similarity_checked_at)->toIso8601String(),
                                                ] : null;
                                            @endphp
                                            @if($profileUrl)
                                                <button type="button"
                                                        @click="openCompare({{ $photo->id }}, '{{ $profileUrl }}', '{{ $uploadedUrl }}', {{ Js::from($photo->full_name) }}, {{ Js::from($existingSimilarity) }})"
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                                                    <img src="{{ $profileUrl }}" class="w-6 h-8 object-cover rounded-sm" alt="profile">
                                                    <span class="text-gray-700">Solishtirish</span>
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">Profil rasmi yo'q</span>
                                            @endif
                                            @if($photo->similarity_score !== null)
                                                <div class="mt-1 text-xs">
                                                    @if($photo->similarity_status === 'match')
                                                        <span class="inline-block px-2 py-0.5 rounded bg-green-100 text-green-800">O'xshash ({{ $photo->similarity_score }}%)</span>
                                                    @elseif($photo->similarity_status === 'mismatch')
                                                        <span class="inline-block px-2 py-0.5 rounded bg-red-100 text-red-800">Farqli ({{ $photo->similarity_score }}%)</span>
                                                    @else
                                                        <span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ $photo->similarity_score }}%</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($photo->status === 'pending')
                                                <div class="flex gap-1 justify-center">
                                                    <form method="POST" action="{{ route('admin.student-photos.approve', $photo->id) }}"
                                                          onsubmit="return confirm('Rasmni tasdiqlaysizmi?')">
                                                        @csrf
                                                        <button type="submit"
                                                                class="inline-flex items-center px-2.5 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700"
                                                                title="Qabul qilish">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        </button>
                                                    </form>
                                                    <button type="button"
                                                            @click="openReject({{ $photo->id }}, {{ Js::from($photo->full_name) }})"
                                                            class="inline-flex items-center px-2.5 py-1.5 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700"
                                                            title="Rad etish">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            @elseif($photo->status === 'approved')
                                                <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-xs font-medium">Tasdiqlangan</span>
                                                @if($photo->reviewed_by_name)
                                                    <div class="text-[11px] text-gray-500 mt-0.5">{{ $photo->reviewed_by_name }}</div>
                                                @endif
                                            @elseif($photo->status === 'rejected')
                                                <span class="inline-block px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-xs font-medium" title="{{ $photo->rejection_reason }}">Rad etilgan</span>
                                                @if($photo->rejection_reason)
                                                    <div class="text-[11px] text-gray-500 mt-0.5 max-w-[180px] truncate" title="{{ $photo->rejection_reason }}">{{ $photo->rejection_reason }}</div>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="px-3 py-8 text-center text-gray-500">Ma'lumot topilmadi</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $photos->links() }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Lightbox: tanlangan rasmni katta ko'rsatish --}}
        <div x-show="lightbox.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
             @click.self="lightbox.open = false"
             @keydown.escape.window="lightbox.open = false">
            <button type="button" @click="lightbox.open = false"
                    class="absolute top-4 right-4 text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <img :src="lightbox.src" :alt="lightbox.alt" class="max-h-[90vh] max-w-[90vw] object-contain rounded-lg shadow-2xl">
        </div>

        {{-- Solishtirish modali --}}
        <div x-show="compare.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
             @click.self="compare.open = false"
             @keydown.escape.window="compare.open = false">
            <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full p-6 relative">
                <button type="button" @click="compare.open = false"
                        class="absolute top-3 right-3 text-gray-400 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <h3 class="text-lg font-semibold text-gray-900 mb-4" x-text="compare.title"></h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 mb-2">Profil rasmi (HEMIS)</div>
                        <img :src="compare.profile" class="mx-auto max-h-[55vh] rounded border border-gray-200" alt="profile">
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-500 mb-2">Tyutor yuklagan rasm</div>
                        <img :src="compare.uploaded" class="mx-auto max-h-[55vh] rounded border border-gray-200" alt="uploaded">
                    </div>
                </div>

                {{-- AI solishtirish bo'limi --}}
                <div class="mt-5 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="text-sm text-gray-700">
                            <strong>AI yuz tahlili</strong> — ArcFace modeli ikki rasmdagi yuzni solishtiradi
                        </div>
                        <button type="button"
                                @click="runSimilarityCheck()"
                                :disabled="compare.loading"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg x-show="!compare.loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            <svg x-show="compare.loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span x-text="compare.loading ? 'Tahlil qilinmoqda...' : (compare.result ? 'Qayta tekshirish' : 'AI bilan tekshirish')"></span>
                        </button>
                    </div>

                    <template x-if="compare.error">
                        <div class="mt-3 rounded-md bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm" x-text="compare.error"></div>
                    </template>

                    <template x-if="compare.result">
                        <div class="mt-3 rounded-md p-4 border"
                             :class="compare.result.match ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl font-bold" :class="compare.result.match ? 'text-green-700' : 'text-red-700'">
                                    <span x-text="compare.result.similarity_percent + '%'"></span>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold" :class="compare.result.match ? 'text-green-800' : 'text-red-800'">
                                        <span x-text="compare.result.match ? 'O\'xshash — ehtimol bir xil shaxs' : 'Farqli — shubhali, e\'tiborga oling'"></span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-0.5">
                                        Masofa: <span x-text="compare.result.distance"></span>,
                                        chegara: <span x-text="compare.result.threshold"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                Bu — yordamchi baho. Yakuniy qaror admin tomonidan qabul qilinadi.
                            </div>
                        </div>
                    </template>

                    <template x-if="!compare.result && !compare.loading && !compare.error">
                        <div class="mt-3 text-xs text-gray-500">
                            Tugmani bosing — tahlil 2-5 soniya oladi.
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Rad etish modali --}}
        <div x-show="reject.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
             @click.self="reject.open = false"
             @keydown.escape.window="reject.open = false">
            <div class="bg-white rounded-lg shadow-2xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Rasmni rad etish</h3>
                <p class="text-sm text-gray-600 mb-4" x-text="reject.name"></p>
                <form method="POST" :action="`{{ url('admin/student-photos') }}/${reject.id}/reject`">
                    @csrf
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rad etish sababi</label>
                    <textarea name="rejection_reason" rows="3" required maxlength="500"
                              class="w-full rounded-md border-gray-300 shadow-sm text-sm mb-4"
                              placeholder="Masalan: Rasm sifati past, yuz aniq ko'rinmaydi, talabaga o'xshamaydi..."></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="reject.open = false"
                                class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                            Bekor qilish
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700">
                            Rad etish
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
