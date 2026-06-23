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
            padding: 10px 12px;
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

                <form method="POST" enctype="multipart/form-data" action="{{ route('student.english-group-application.store') }}" class="px-5 py-5">
                    @csrf

                    @if(session('success'))
                        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="ega-btn">Arizani yuborish</button>
                    </div>
                </form>
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
</x-student-app-layout>
