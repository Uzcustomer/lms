<x-app-layout>
    <div class="ega-page p-4 sm:ml-64">
        <div class="mt-14">
            <div class="ega-hero flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Ingliz guruhga o'tish arizalari</h1>
                    <p class="text-sm text-gray-500 mt-1">Talabalar yuborgan arizalarni ko'rish, saralash va ko'rib chiqish oynasi</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.english-group-applications.export') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                       title="Barcha arizalarni Excel fayliga yuklash">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12 4-4m-4 4-4-4M5 20h14"/>
                        </svg>
                        Excel yuklash
                    </a>
                    <a href="{{ route('admin.english-group-applications.documents-zip') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                       title="Sertifikati bor arizachilar hujjatlarini ZIP qilib yuklash">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9ZM8 9h8M8 12h8M8 15h5"/>
                        </svg>
                        Hujjatlarni yuklash
                    </a>
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

            @if($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <div class="font-semibold">Hujjatni yuklashda xatolik:</div>
                    <ul class="mt-1 list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="ega-stats grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                <a href="{{ route('admin.english-group-applications.index') }}"
                   class="block rounded-xl border-2 p-4 transition hover:shadow-md {{ request('status') === 'all' ? 'border-sky-500 bg-sky-50' : 'border-sky-200 bg-white hover:border-sky-300' }}">
                    <div class="text-xs uppercase font-semibold text-sky-600">Jami</div>
                    <div class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['total'] }}</div>
                </a>
                <a href="{{ route('admin.english-group-applications.index', ['status' => 'pending']) }}"
                   class="block rounded-xl border-2 p-4 transition hover:shadow-md {{ request('status', 'pending') === 'pending' ? 'border-amber-500 bg-amber-50' : 'border-amber-200 bg-white hover:border-amber-300' }}">
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

            <div class="ega-filter bg-white rounded-2xl border border-slate-200 shadow-sm mb-4">
                <form method="GET" action="{{ route('admin.english-group-applications.index') }}" class="p-4 grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Qidiruv</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Talaba, HEMIS, guruh, telefon..."
                               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Fakultet</label>
                        <select name="faculty_name" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($filterOptions['faculties'] as $faculty)
                                <option value="{{ $faculty }}" @selected(request('faculty_name') === $faculty)>{{ $faculty }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Yo'nalish</label>
                        <select name="specialty_name" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($filterOptions['specialties'] as $specialty)
                                <option value="{{ $specialty }}" @selected(request('specialty_name') === $specialty)>{{ $specialty }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Kurs</label>
                        <select name="course_name" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($filterOptions['courses'] as $course)
                                <option value="{{ $course }}" @selected(request('course_name') === $course)>{{ $course }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Holat</label>
                        <select name="status" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                            <option value="all" @selected(request('status') === 'all')>Barchasi</option>
                            <option value="pending" @selected(request('status', 'pending') === 'pending')>Kutilmoqda</option>
                            <option value="approved" @selected(request('status') === 'approved')>Qabul qilingan</option>
                            <option value="rejected" @selected(request('status') === 'rejected')>Rad etilgan</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Til darajasi</label>
                        <select name="english_level" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($englishLevels as $value => $label)
                                <option value="{{ $value }}" @selected(request('english_level') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-12 flex flex-wrap gap-2">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-sky-600 text-white font-semibold hover:bg-sky-700 transition">Filtrlash</button>
                        <a href="{{ route('admin.english-group-applications.index', ['status' => 'pending']) }}" class="px-4 py-2 rounded-xl bg-slate-200 text-slate-700 font-semibold hover:bg-slate-300 transition">Tozalash</a>
                    </div>
                </form>
            </div>

            <div class="ega-table-card bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
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
                                <tr x-data="{ rejectOpen: false, uploadOpen: false }" class="hover:bg-slate-50 transition">
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
                                            <button type="button"
                                                    @click="uploadOpen = true"
                                                    title="{{ $application->certificate_pdf_path ? 'Hujjatni almashtirish' : 'Hujjat yuklash' }}"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-white text-xs font-semibold transition"
                                                    style="background:#2563eb;border:0;border-radius:8px;">
                                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0L7 9m5-5 5 5M5 15v4h14v-4"/>
                                                </svg>
                                                {{ $application->certificate_pdf_path ? 'Almashtirish' : 'Hujjat yuklash' }}
                                            </button>
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
                                                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg transition"
                                                        style="background: transparent; color: #dc2626;">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>

                                        <div x-show="uploadOpen"
                                             x-cloak
                                             class="fixed inset-0 z-50 flex items-center justify-center px-4"
                                             style="background:rgba(15,23,42,.58);backdrop-filter:blur(4px);"
                                             @keydown.escape.window="uploadOpen = false">
                                            <div @click.outside="uploadOpen = false"
                                                 style="width:min(460px,calc(100vw - 32px));overflow:hidden;border-radius:14px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.32);text-align:left;">
                                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 18px;color:#fff;background:linear-gradient(135deg,#1f4f91,#2563eb);">
                                                    <div>
                                                        <div style="font-size:16px;font-weight:800;">{{ $application->certificate_pdf_path ? 'Hujjatni almashtirish' : 'Hujjat yuklash' }}</div>
                                                        <div style="margin-top:3px;font-size:12px;color:#dbeafe;">{{ $application->full_name }}</div>
                                                    </div>
                                                    <button type="button" @click="uploadOpen = false"
                                                            style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:0;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;cursor:pointer;">
                                                        <svg style="width:17px;height:17px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <form method="POST"
                                                      action="{{ route('admin.english-group-applications.certificate.upload', $application->id) }}"
                                                      enctype="multipart/form-data"
                                                      style="padding:18px;">
                                                    @csrf
                                                    <label style="display:block;margin-bottom:7px;font-size:12px;font-weight:700;color:#334155;">Sertifikat hujjati</label>
                                                    <label style="min-height:118px;display:flex;cursor:pointer;flex-direction:column;align-items:center;justify-content:center;border:2px dashed #bfdbfe;border-radius:11px;background:#f8fbff;padding:16px;text-align:center;">
                                                        <svg style="width:28px;height:28px;margin-bottom:8px;color:#2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0L7 9m5-5 5 5M5 15v4h14v-4"/>
                                                        </svg>
                                                        <span style="font-size:13px;font-weight:700;color:#334155;">PDF faylni tanlang</span>
                                                        <span style="margin-top:4px;font-size:11px;color:#64748b;">Maksimal hajm: 10 MB</span>
                                                        <input type="file" name="certificate_pdf" accept=".pdf,application/pdf" required
                                                               style="margin-top:12px;width:100%;font-size:12px;color:#475569;">
                                                    </label>
                                                    @if($application->certificate_pdf_path)
                                                        <div style="margin-top:10px;border-radius:8px;background:#fffbeb;padding:9px 11px;font-size:11px;color:#92400e;">
                                                            Yangi fayl yuklansa, avvalgi sertifikat almashtiriladi.
                                                        </div>
                                                    @endif
                                                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                                                        <button type="button" @click="uploadOpen = false"
                                                                style="height:37px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:0 15px;font-size:12px;font-weight:700;color:#475569;cursor:pointer;">
                                                            Bekor qilish
                                                        </button>
                                                        <button type="submit"
                                                                style="height:37px;border:1px solid #2563eb;border-radius:8px;background:#2563eb;padding:0 16px;font-size:12px;font-weight:700;color:#fff;cursor:pointer;">
                                                            Saqlash
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
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
<style>
    .ega-page {
        min-height: calc(100vh - 72px);
        background: #eef3f9;
        color: #172b4d;
    }
    .ega-hero {
        padding: 22px 24px;
        border-radius: 16px;
        background: linear-gradient(135deg, #102a56 0%, #1e5da8 72%, #3181c8 100%);
        box-shadow: 0 12px 28px rgba(16, 42, 86, .18);
        color: #fff;
    }
    .ega-hero h1,
    .ega-hero p {
        color: #fff !important;
    }
    .ega-hero p {
        opacity: .78;
    }
    .ega-hero a {
        border-radius: 9px !important;
        box-shadow: 0 5px 12px rgba(0, 0, 0, .14);
    }
    .ega-hero a:hover {
        transform: translateY(-1px);
    }
    .ega-stats > a {
        position: relative;
        overflow: hidden;
        border: 1px solid #d8e2ef !important;
        border-radius: 14px !important;
        background: #fff !important;
        box-shadow: 0 5px 16px rgba(31, 61, 100, .07);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .ega-stats > a:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 22px rgba(31, 61, 100, .12);
    }
    .ega-stats > a::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: #1d63b6;
    }
    .ega-stats > a:nth-child(2)::before { background: #f59e0b; }
    .ega-stats > a:nth-child(3)::before { background: #10b981; }
    .ega-stats > a:nth-child(4)::before { background: #ef4444; }
    .ega-stats > a div:last-child {
        color: #102a56 !important;
    }
    .ega-filter form {
        display: flex !important;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }
    .ega-filter form > div {
        min-width: 170px;
        flex: 1 1 180px;
    }
    .ega-filter form > div:first-child {
        flex: 2 1 320px;
    }
    .ega-filter form > div:last-child {
        flex: 0 0 100%;
        min-width: 100%;
    }
    .ega-filter {
        border-color: #d8e2ef !important;
        border-radius: 14px !important;
        box-shadow: 0 5px 16px rgba(31, 61, 100, .06) !important;
    }
    .ega-filter label {
        color: #385579 !important;
        letter-spacing: .04em;
    }
    .ega-filter input,
    .ega-filter select {
        border-color: #b9c9dc !important;
        border-radius: 9px !important;
        color: #233d60;
        background: #fbfdff;
    }
    .ega-filter button {
        border-radius: 9px !important;
        background: #1d63b6 !important;
    }
    .ega-filter a {
        border-radius: 9px !important;
    }
    .ega-table-card {
        border-color: #d8e2ef !important;
        border-radius: 14px !important;
        box-shadow: 0 7px 20px rgba(31, 61, 100, .07) !important;
    }
    .ega-table-card table {
        color: #294361;
    }
    .ega-table-card thead {
        background: #e8eff7 !important;
        color: #385579 !important;
    }
    .ega-table-card thead th {
        padding-top: 13px !important;
        padding-bottom: 13px !important;
        font-size: 10px !important;
        letter-spacing: .06em;
    }
    .ega-table-card tbody tr {
        border-color: #e2eaf3 !important;
        transition: background .16s ease;
    }
    .ega-table-card tbody tr:hover {
        background: #f4f8fc !important;
    }
    .ega-table-card tbody td {
        padding-top: 15px !important;
        padding-bottom: 15px !important;
    }
    .ega-table-card tbody td:first-child > div:first-child {
        color: #123d72 !important;
    }
    .ega-table-card tbody td a {
        color: #1769b0;
    }
    .ega-table-card form button,
    .ega-table-card td > div > form button {
        border-radius: 8px !important;
    }
    .ega-table-card > div:last-child {
        background: #f5f8fc !important;
        border-color: #d8e2ef !important;
    }
    @media (max-width: 767px) {
        .ega-page {
            padding: 12px !important;
        }
        .ega-hero {
            padding: 18px;
        }
        .ega-hero > div:last-child {
            width: 100%;
        }
        .ega-hero > div:last-child a {
            flex: 1;
            justify-content: center;
        }
    }
</style>
</x-app-layout>
