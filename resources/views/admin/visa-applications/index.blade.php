<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Viza arizalar</h2>
            <a href="{{ route('admin.international-students.index') }}" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;font-size:12px;font-weight:600;color:#475569;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;transition:all 0.15s;">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                Xalqaro talabalar
            </a>
        </div>
    </x-slot>

    @php
        $statusMeta = [
            'pending'   => ['label' => 'Kutilmoqda',    'bg' => '#fef3c7', 'fg' => '#92400e', 'border' => '#fde68a'],
            'reviewing' => ['label' => 'Ko\'rilmoqda',  'bg' => '#dbeafe', 'fg' => '#1e40af', 'border' => '#bfdbfe'],
            'approved'  => ['label' => 'Qabul qilindi', 'bg' => '#d1fae5', 'fg' => '#065f46', 'border' => '#a7f3d0'],
            'rejected'  => ['label' => 'Rad etilgan',   'bg' => '#fee2e2', 'fg' => '#991b1b', 'border' => '#fecaca'],
        ];
        $total = array_sum($counts);
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-white rounded-xl border border-emerald-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 flex items-center gap-3" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
                        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm font-semibold text-emerald-800">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            {{-- FILTER + STATS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                    <div class="font-bold text-gray-800 text-sm">Holat bo'yicha filtr</div>
                </div>
                <div class="p-3 flex flex-wrap gap-2">
                    <a href="{{ route('admin.visa-applications.index') }}"
                       class="px-3 py-1.5 text-xs font-bold rounded-lg border transition flex items-center gap-2"
                       style="background:{{ !$status ? '#2b5ea7' : '#fff' }};color:{{ !$status ? '#fff' : '#475569' }};border-color:{{ !$status ? '#2b5ea7' : '#e2e8f0' }};">
                        Hammasi
                        <span style="background:rgba(255,255,255,0.2);padding:1px 6px;border-radius:999px;{{ !$status ? '' : 'background:#f1f5f9;color:#475569;' }}">{{ $total }}</span>
                    </a>
                    @foreach($statusMeta as $key => $m)
                        <a href="{{ route('admin.visa-applications.index', ['status' => $key]) }}"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border transition flex items-center gap-2"
                           style="background:{{ $status === $key ? $m['fg'] : $m['bg'] }};color:{{ $status === $key ? '#fff' : $m['fg'] }};border-color:{{ $m['border'] }};">
                            {{ $m['label'] }}
                            <span style="background:rgba(255,255,255,0.2);padding:1px 6px;border-radius:999px;{{ $status === $key ? '' : 'background:rgba(0,0,0,0.08);' }}">{{ $counts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ARIZALAR --}}
            @if($applications->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
                    <svg class="w-14 h-14 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                    </svg>
                    <div class="text-sm font-semibold text-slate-600">Bu holatda arizalar yo'q</div>
                </div>
            @else
                <div class="space-y-2" x-data="{ open: null }">
                    @foreach($applications as $app)
                        @php $m = $statusMeta[$app->status] ?? $statusMeta['pending']; @endphp
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                            {{-- ACCORDION HEADER --}}
                            <button type="button"
                                    @click="open = (open === {{ $app->id }}) ? null : {{ $app->id }}"
                                    class="w-full px-4 sm:px-5 py-3 flex items-center justify-between gap-3 hover:bg-slate-50 transition text-left">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex flex-col items-center w-12 flex-shrink-0">
                                        <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">№</span>
                                        <span class="text-base font-bold text-slate-800">{{ $app->application_number }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm text-slate-800 truncate">
                                            {{ $app->last_name }} {{ $app->first_name }} {{ $app->middle_name }}
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                            <span>Student ID: <strong>{{ $app->student_number }}</strong></span>
                                            <span class="hidden sm:inline">·</span>
                                            <span>{{ $app->created_at->format('d.m.Y H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide"
                                          style="background:{{ $m['bg'] }};color:{{ $m['fg'] }};border:1px solid {{ $m['border'] }};">
                                        {{ $m['label'] }}
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform" :class="open === {{ $app->id }} ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                    </svg>
                                </div>
                            </button>

                            {{-- ACCORDION BODY --}}
                            <div x-show="open === {{ $app->id }}" x-collapse class="border-t border-gray-100">
                                <div class="p-4 sm:p-5 space-y-4">
                                    {{-- TALABA MA'LUMOTLARI --}}
                                    <div>
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2">Talaba ma'lumotlari</div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm bg-slate-50 rounded-lg p-3 border border-slate-200">
                                            <div><span class="text-slate-500">Familiya:</span> <strong class="text-slate-800">{{ $app->last_name }}</strong></div>
                                            <div><span class="text-slate-500">Ism:</span> <strong class="text-slate-800">{{ $app->first_name }}</strong></div>
                                            <div><span class="text-slate-500">Otasining ismi:</span> <strong class="text-slate-800">{{ $app->middle_name ?: '—' }}</strong></div>
                                            <div><span class="text-slate-500">Tug'ilgan sana:</span> <strong class="text-slate-800">{{ optional($app->birth_date)->format('d.m.Y') ?? '—' }}</strong></div>
                                            <div><span class="text-slate-500">Pasport raqami:</span> <strong class="text-slate-800">{{ $app->passport_number }}</strong></div>
                                            <div><span class="text-slate-500">Student ID:</span> <strong class="text-slate-800">{{ $app->student_number }}</strong></div>
                                            <div><span class="text-slate-500">Telefon raqami:</span> <strong class="text-slate-800">{{ $app->phone_number }}</strong></div>
                                            <div>
                                                <span class="text-slate-500">{{ ucfirst($app->messenger_type ?? 'telegram') }}:</span>
                                                @php
                                                    $uname = ltrim($app->messenger_username ?? '', '@');
                                                    $tgLink = $uname ? 'https://t.me/' . $uname : null;
                                                    $waLink = $uname && $app->phone_number ? 'https://wa.me/' . preg_replace('/\D/', '', $app->phone_number) : null;
                                                    $link = ($app->messenger_type === 'whatsapp') ? $waLink : $tgLink;
                                                @endphp
                                                @if($link)
                                                    <a href="{{ $link }}" target="_blank" rel="noopener" class="font-bold text-blue-600 hover:underline">@{{ $uname }}</a>
                                                @else
                                                    <strong class="text-slate-800">@{{ $uname ?: '—' }}</strong>
                                                @endif
                                            </div>
                                            <div class="sm:col-span-2"><span class="text-slate-500">Yuborilgan:</span> <strong class="text-slate-800">{{ $app->created_at->format('d.m.Y H:i') }}</strong></div>
                                        </div>
                                    </div>

                                    {{-- FAYLLAR --}}
                                    <div>
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2">Yuklangan fayllar</div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @if($app->passport_pdf_path)
                                                <a href="{{ route('admin.visa-applications.file', [$app, 'passport']) }}" target="_blank" rel="noopener"
                                                   class="flex items-center gap-3 p-3 bg-white border-2 border-slate-200 hover:border-blue-400 rounded-lg transition group">
                                                    <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M9 0a2 2 0 0 0-2 2v2H3v18h18V4H9V2a2 2 0 0 0 2-2H9zm2 7h2v2h2v2h-2v6h-2v-6H9V9h2V7z"/></svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-semibold text-slate-800">Passport copies</div>
                                                        <div class="text-[11px] text-slate-500 truncate">PDF · yangi tabda ochiladi</div>
                                                    </div>
                                                    <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                                </a>
                                            @endif
                                            @if($app->application_pdf_path)
                                                <a href="{{ route('admin.visa-applications.file', [$app, 'application']) }}" target="_blank" rel="noopener"
                                                   class="flex items-center gap-3 p-3 bg-white border-2 border-slate-200 hover:border-blue-400 rounded-lg transition group">
                                                    <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-semibold text-slate-800">Filled application</div>
                                                        <div class="text-[11px] text-slate-500 truncate">PDF · yangi tabda ochiladi</div>
                                                    </div>
                                                    <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ADMIN NOTE (mavjud bo'lsa) --}}
                                    @if($app->admin_note || $app->reviewed_at)
                                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                            <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wide mb-1">Admin izoh</div>
                                            <div class="text-sm text-amber-900">{{ $app->admin_note ?: '—' }}</div>
                                            @if($app->reviewed_at)
                                                <div class="text-[11px] text-amber-700 mt-1">Ko'rib chiqilgan: {{ $app->reviewed_at->format('d.m.Y H:i') }}</div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- HARAKATLAR --}}
                                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
                                        <div x-data="{ showApprove: false, showReject: false }" class="flex flex-wrap gap-2 w-full">
                                            {{-- Qabul qilish --}}
                                            <button type="button" @click="showApprove = !showApprove; showReject = false"
                                                    class="px-3 py-2 text-xs font-bold text-white rounded-lg transition flex items-center gap-1.5"
                                                    style="background:linear-gradient(135deg,#10b981,#059669);">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                Qabul qilish
                                            </button>
                                            {{-- Rad etish --}}
                                            <button type="button" @click="showReject = !showReject; showApprove = false"
                                                    class="px-3 py-2 text-xs font-bold text-white rounded-lg transition flex items-center gap-1.5"
                                                    style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                Rad etish
                                            </button>
                                            {{-- O'chirish --}}
                                            <form method="POST" action="{{ route('admin.visa-applications.destroy', $app) }}"
                                                  onsubmit="return confirm('Arizani butunlay o\'chirishni tasdiqlaysizmi?');"
                                                  class="inline-block">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="px-3 py-2 text-xs font-bold rounded-lg transition flex items-center gap-1.5"
                                                        style="background:#fff;border:1px solid #cbd5e1;color:#475569;">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                    O'chirish
                                                </button>
                                            </form>

                                            {{-- Approve form (collapse) --}}
                                            <form x-show="showApprove" x-cloak method="POST" action="{{ route('admin.visa-applications.approve', $app) }}"
                                                  class="w-full bg-emerald-50 border border-emerald-200 rounded-lg p-3 mt-2 flex flex-col sm:flex-row gap-2">
                                                @csrf
                                                <input type="text" name="admin_note" placeholder="Izoh (ixtiyoriy)"
                                                       class="flex-1 px-3 py-2 text-sm border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                                <button type="submit" class="px-4 py-2 text-sm font-bold text-white rounded-lg whitespace-nowrap"
                                                        style="background:linear-gradient(135deg,#10b981,#059669);">Tasdiqlash</button>
                                            </form>

                                            {{-- Reject form (collapse) --}}
                                            <form x-show="showReject" x-cloak method="POST" action="{{ route('admin.visa-applications.reject', $app) }}"
                                                  class="w-full bg-red-50 border border-red-200 rounded-lg p-3 mt-2 flex flex-col sm:flex-row gap-2">
                                                @csrf
                                                <input type="text" name="admin_note" placeholder="Sabab (tavsiya etiladi)"
                                                       class="flex-1 px-3 py-2 text-sm border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                                                <button type="submit" class="px-4 py-2 text-sm font-bold text-white rounded-lg whitespace-nowrap"
                                                        style="background:linear-gradient(135deg,#ef4444,#dc2626);">Tasdiqlash</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $applications->links() }}</div>
            @endif

        </div>
    </div>
</x-app-layout>
