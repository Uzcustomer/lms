<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">Ingliz tili guruhiga o'tish uchun ariza</h2>
    </x-slot>

    <style>
        .ega-card {
            box-shadow: 0 24px 60px -28px rgba(16, 185, 129, 0.28), 0 12px 28px -16px rgba(15, 23, 42, 0.12);
        }
        .ega-hero {
            background: linear-gradient(135deg, #0f766e 0%, #10b981 100%);
        }
        .ega-input {
            width: 100%;
            padding: 8px;
            font-size: 14px;
            background: #fff;
            border: 1.5px solid #dbe4f0;
            border-radius: 10px;
            color: #0f172a;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .ega-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
        }
        .ega-input[readonly] {
            background: #f8fafc;
            color: #475569;
        }
        .ega-label {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .ega-hint {
            margin-top: 4px;
            font-size: 11px;
            color: #64748b;
        }
        .ega-btn {
            background: linear-gradient(135deg, #0f766e, #10b981);
            color: #fff;
            font-weight: 700;
            padding: 12px 18px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 0 4px 14px -4px rgba(16, 185, 129, 0.55);
        }
        .ega-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px -4px rgba(16, 185, 129, 0.6);
        }
        .ega-status-pending { background:#fef3c7; color:#92400e; }
        .ega-status-approved { background:#d1fae5; color:#065f46; }
        .ega-status-rejected { background:#fee2e2; color:#991b1b; }
        .ega-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 60;
        }
        .ega-modal {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 60px -28px rgba(15, 23, 42, 0.45);
        }
        .ega-modal-head {
            background: linear-gradient(135deg, #0f766e 0%, #10b981 100%);
            color: #fff;
            padding: 18px 20px;
        }
        .ega-modal-body {
            padding: 20px;
            color: #334155;
            font-size: 14px;
            line-height: 1.65;
        }
        .ega-modal-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            background: #0f172a;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border-radius: 10px;
            padding: 10px 18px;
            border: none;
            cursor: pointer;
        }
    </style>

    <div class="px-3 py-4 sm:py-6">
        <div class="max-w-4xl mx-auto space-y-4">
            <div class="ega-card bg-white rounded-2xl overflow-hidden">
                <div class="ega-hero px-5 py-4 text-white">
                    <h1 class="text-base sm:text-lg font-bold leading-snug">Ingliz tili guruhiga o'tish uchun ariza</h1>
                    <p class="text-xs sm:text-sm text-white/90 mt-1 leading-snug">
                        Ma'lumotlaringiz avtomatik to'ldiriladi. Ingliz tilini bilish darajangizni kiriting va agar mavjud bo'lsa sertifikatingizni PDF ko'rinishida yuklang.
                    </p>
                </div>

                <div class="px-5 py-5">
                    @if($errors->any())
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($canSubmit)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('student.english-group-application.store') }}">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-[5px]">
                                <div class="sm:col-span-2">
                                    <label class="ega-label">F.I.SH</label>
                                    <input type="text" class="ega-input" value="{{ $student->full_name ?? '-' }}" readonly>
                                </div>
                                <div>
                                    <label class="ega-label">Fakultet</label>
                                    <input type="text" class="ega-input" value="{{ $student->department_name ?? '-' }}" readonly>
                                </div>
                                <div>
                                    <label class="ega-label">Yo'nalish</label>
                                    <input type="text" class="ega-input" value="{{ $student->specialty_name ?? '-' }}" readonly>
                                </div>
                                <div>
                                    <label class="ega-label">Kurs</label>
                                    <input type="text" class="ega-input" value="{{ $student->level_name ?? '-' }}" readonly>
                                </div>
                                <div>
                                    <label class="ega-label">Semestr</label>
                                    <input type="text" class="ega-input" value="{{ $student->semester_name ?? '-' }}" readonly>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="ega-label">Guruh</label>
                                    <input type="text" class="ega-input" value="{{ $student->group_name ?? '-' }}" readonly>
                                </div>
                                <div>
                                    <label for="english_level" class="ega-label">Ingliz tilini bilish darajasi</label>
                                    <select id="english_level" name="english_level" class="ega-input">
                                        <option value="">Tanlanmagan</option>
                                        @foreach($englishLevels as $value => $label)
                                            <option value="{{ $value }}" @selected(old('english_level') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="ega-hint">Ixtiyoriy maydon.</div>
                                </div>
                                <div>
                                    <label for="certificate_pdf" class="ega-label">Til sertifikati (ixtiyoriy)</label>
                                    <input id="certificate_pdf" type="file" name="certificate_pdf" accept="application/pdf" class="ega-input">
                                    <div class="ega-hint">Faqat PDF, maksimal 2 MB.</div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="ega-label" for="phone_number">Telefon raqam</label>
                                    <input id="phone_number" type="text" name="phone_number" class="ega-input" value="{{ old('phone_number', $student->phone ?? '') }}" placeholder="+998...">
                                </div>
                            </div>

                            <div class="flex justify-end" style="margin-top: 5px;">
                                <button type="submit" class="ega-btn">Arizani yuborish</button>
                            </div>
                        </form>
                    @elseif($latest)
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
                            <div class="flex items-start justify-between gap-4 flex-wrap">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Ariza holati</h3>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Ingliz tili guruhiga o'tish uchun arizangiz qabul qilingan. Quyida joriy holati ko'rinadi.
                                    </p>
                                </div>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ega-status-{{ $latest->status }}">
                                    @if($latest->status === 'approved')
                                        Qabul qilingan
                                    @elseif($latest->status === 'rejected')
                                        Rad etilgan
                                    @else
                                        Kutilmoqda
                                    @endif
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="ega-label">Telefon raqam</div>
                                    <div class="text-sm text-slate-700">{{ $latest->phone_number ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="ega-label">Ingliz tili darajasi</div>
                                    <div class="text-sm text-slate-700">{{ $englishLevels[$latest->english_level] ?? 'Tanlanmagan' }}</div>
                                </div>
                                <div>
                                    <div class="ega-label">Yuborilgan sana</div>
                                    <div class="text-sm text-slate-700">{{ $latest->created_at?->format('d.m.Y H:i') }}</div>
                                </div>
                                <div>
                                    <div class="ega-label">Til sertifikati</div>
                                    <div class="text-sm text-slate-700">{{ $latest->certificate_pdf_path ? 'Yuklangan' : 'Yuklanmagan' }}</div>
                                </div>
                                @if($latest->status === 'rejected' && $latest->admin_note)
                                    <div class="sm:col-span-2">
                                        <div class="ega-label">Rad etish sababi</div>
                                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                            {{ $latest->admin_note }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($applications->isNotEmpty())
                <div class="ega-card bg-white rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-200">
                        <h3 class="text-sm font-bold text-slate-800">Oldingi arizalaringiz</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3 text-left">Sana</th>
                                    <th class="px-4 py-3 text-left">Daraja</th>
                                    <th class="px-4 py-3 text-left">Holat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($applications as $application)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-4 py-3 text-slate-700">{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $englishLevels[$application->english_level] ?? 'Tanlanmagan' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ega-status-{{ $application->status }}">
                                                @if($application->status === 'approved')
                                                    Qabul qilingan
                                                @elseif($application->status === 'rejected')
                                                    Rad etilgan
                                                @else
                                                    Kutilmoqda
                                                @endif
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div id="egaSuccessModal" class="ega-modal-backdrop">
            <div class="ega-modal">
                <div class="ega-modal-head">
                    <h3 class="text-lg font-bold">Ariza qabul qilindi</h3>
                </div>
                <div class="ega-modal-body">
                    <p>{{ session('success') }}</p>
                    <div class="mt-5 flex justify-end">
                        <button type="button" class="ega-modal-btn" onclick="document.getElementById('egaSuccessModal').remove()">Yopish</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-student-app-layout>
