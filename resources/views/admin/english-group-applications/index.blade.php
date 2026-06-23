<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Ingliz guruhga o'tish arizalari</h1>
                    <p class="text-sm text-gray-500 mt-1">Talabalar yuborgan arizalarni ko'rish, saralash va ko'rib chiqish oynasi</p>
                </div>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                <a href="{{ route('admin.english-group-applications.index') }}"
                   class="block rounded-xl border-2 p-4 transition hover:shadow-md {{ !request('status') ? 'border-sky-500 bg-sky-50' : 'border-sky-200 bg-white hover:border-sky-300' }}">
                    <div class="text-xs uppercase font-semibold text-sky-600">Jami</div>
                    <div class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['total'] }}</div>
                </a>
                <a href="{{ route('admin.english-group-applications.index', ['status' => 'pending']) }}"
                   class="block rounded-xl border-2 p-4 transition hover:shadow-md {{ request('status') === 'pending' ? 'border-amber-500 bg-amber-50' : 'border-amber-200 bg-white hover:border-amber-300' }}">
                    <div class="text-xs uppercase font-semibold text-amber-600">Kutilmoqda</div>
                    <div class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['pending'] }}</div>
                </a>
                <a href="{{ route('admin.english-group-applications.index', ['status' => 'approved']) }}"
                   class="block rounded-xl border-2 p-4 transition hover:shadow-md {{ request('status') === 'approved' ? 'border-emerald-500 bg-emerald-50' : 'border-emerald-200 bg-white hover:border-emerald-300' }}">
                    <div class="text-xs uppercase font-semibold text-emerald-600">Qabul qilingan</div>
                    <div class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['approved'] }}</div>
                </a>
                <a href="{{ route('admin.english-group-applications.index', ['status' => 'rejected']) }}"
                   class="block rounded-xl border-2 p-4 transition hover:shadow-md {{ request('status') === 'rejected' ? 'border-rose-500 bg-rose-50' : 'border-rose-200 bg-white hover:border-rose-300' }}">
                    <div class="text-xs uppercase font-semibold text-rose-600">Rad etilgan</div>
                    <div class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['rejected'] }}</div>
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm mb-4">
                <form method="GET" action="{{ route('admin.english-group-applications.index') }}" class="p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Qidiruv</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Talaba, hemis, guruh, telefon..."
                               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Holat</label>
                        <select name="status" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            <option value="pending" @selected(request('status') === 'pending')>Kutilmoqda</option>
                            <option value="approved" @selected(request('status') === 'approved')>Qabul qilingan</option>
                            <option value="rejected" @selected(request('status') === 'rejected')>Rad etilgan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Til darajasi</label>
                        <select name="english_level" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($englishLevels as $value => $label)
                                <option value="{{ $value }}" @selected(request('english_level') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-4 flex flex-wrap gap-2">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-sky-600 text-white font-semibold hover:bg-sky-700 transition">Filtrlash</button>
                        <a href="{{ route('admin.english-group-applications.index') }}" class="px-4 py-2 rounded-xl bg-slate-200 text-slate-700 font-semibold hover:bg-slate-300 transition">Tozalash</a>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Talaba</th>
                                <th class="px-4 py-3 text-left">Aloqa</th>
                                <th class="px-4 py-3 text-left">O'qish ma'lumoti</th>
                                <th class="px-4 py-3 text-left">Til darajasi</th>
                                <th class="px-4 py-3 text-left">Sertifikat</th>
                                <th class="px-4 py-3 text-left">Holat</th>
                                <th class="px-4 py-3 text-left">Sana</th>
                                <th class="px-4 py-3 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($applications as $application)
                                <tr x-data="{ rejectOpen: false }" class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-semibold text-slate-800">{{ $application->full_name }}</div>
                                        <div class="text-xs text-slate-500 mt-1">HEMIS: {{ $application->student_hemis_id ?: '-' }}</div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="text-slate-700">{{ $application->phone_number ?: '-' }}</div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="text-slate-800">{{ $application->faculty_name ?: '-' }}</div>
                                        <div class="text-xs text-slate-500 mt-1">{{ $application->specialty_name ?: '-' }}</div>
                                        <div class="text-xs text-sky-700 mt-1">{{ $application->course_name ?: '-' }} | {{ $application->semester_name ?: '-' }}</div>
                                        <div class="text-xs text-slate-500 mt-1">{{ $application->group_name ?: '-' }}</div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-slate-700">
                                        {{ $englishLevels[$application->english_level] ?? 'Tanlanmagan' }}
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if($application->certificate_pdf_path)
                                            <a href="{{ route('admin.english-group-applications.certificate', $application->id) }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="text-sky-600 hover:text-sky-800 underline text-sm font-medium">
                                                Sertifikatni ochish
                                            </a>
                                        @else
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">Yo'q</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if($application->status === 'approved')
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Qabul qilingan</span>
                                        @elseif($application->status === 'rejected')
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700">Rad etilgan</span>
                                        @else
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Kutilmoqda</span>
                                        @endif
                                        @if($application->rejection_reason_label)
                                            <div class="mt-2 text-xs text-rose-600">{{ $application->rejection_reason_label }}</div>
                                        @endif
                                        @if($application->admin_note)
                                            <div class="mt-2 text-xs text-rose-600">{{ $application->admin_note }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-slate-600">
                                        {{ $application->created_at?->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-4 py-4 align-top text-right">
                                        <div class="flex items-center justify-end gap-2 flex-wrap">
                                            @if($application->status !== 'approved')
                                                <form method="POST" action="{{ route('admin.english-group-applications.approve', $application->id) }}">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                                                        Qabul qilish
                                                    </button>
                                                </form>
                                            @endif
                                            @if($application->status !== 'rejected')
                                                <button type="button"
                                                        @click="rejectOpen = true"
                                                        class="px-3 py-1.5 rounded-lg text-white text-xs font-semibold transition"
                                                        style="background: #dc2626; border-radius: 8px;">
                                                    Rad etish
                                                </button>
                                            @endif
                                            <form method="POST"
                                                  action="{{ route('admin.english-group-applications.destroy', $application->id) }}"
                                                  onsubmit="return confirm('Ariza va yuklangan fayl butunlay o\\'chirilsinmi?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        title="O'chirish"
                                                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-white transition"
                                                        style="background: #0f172a;">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>

                                        @if($application->status !== 'rejected')
                                            <div x-show="rejectOpen"
                                                 x-cloak
                                                 class="fixed inset-0 z-50 flex items-center justify-center px-4"
                                                 style="background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px);">
                                                <div @click.outside="rejectOpen = false"
                                                     class="w-full max-w-md rounded-2xl bg-white text-left shadow-2xl overflow-hidden">
                                                    <div class="px-5 py-4" style="background: linear-gradient(135deg, #b91c1c 0%, #ef4444 100%); color: white;">
                                                        <div class="text-base font-bold">Arizani rad etish</div>
                                                        <div class="text-sm text-white/90 mt-1">{{ $application->full_name }}</div>
                                                    </div>
                                                    <form method="POST" action="{{ route('admin.english-group-applications.reject', $application->id) }}" class="p-5">
                                                        @csrf
                                                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">Rad etish sababi</label>
                                                        <label class="flex items-center gap-2 mb-3 text-sm text-slate-700">
                                                            <input type="checkbox" name="rejection_reason_code" value="interview_failed" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                                            <span>Suhbatdan o'ta olmadi</span>
                                                        </label>
                                                        <textarea name="admin_note"
                                                                  rows="4"
                                                                  placeholder="Ixtiyoriy izoh..."
                                                                  class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">{{ old('admin_note') }}</textarea>
                                                        <div class="mt-4 flex items-center justify-end gap-2">
                                                            <button type="button"
                                                                    @click="rejectOpen = false"
                                                                    class="px-4 py-2 rounded-xl bg-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-300 transition">
                                                                Bekor qilish
                                                            </button>
                                                            <button type="submit"
                                                                    class="px-4 py-2 rounded-xl text-white text-sm font-semibold transition"
                                                                    style="background: #dc2626;">
                                                                Rad etish
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-slate-500">Arizalar topilmadi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-200 bg-slate-50">
                    {{ $applications->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
