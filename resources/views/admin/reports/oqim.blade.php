<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Oqim hisoboti — talabalarni oqim va guruhlarga taqsimlash
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? '') == $type->education_type_code ? 'selected' : '' }}>
                                        {{ $type->education_type_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1 1 260px; min-width: 240px; max-width: 460px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width: 100%;" {{ isset($dekanFacultyId) && $dekanFacultyId ? 'disabled' : '' }}>
                                @if(isset($dekanFacultyId) && $dekanFacultyId)
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" selected>{{ $faculty->name }}</option>
                                    @endforeach
                                @else
                                    <option value="">Barchasi</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @if(isset($dekanFacultyId) && $dekanFacultyId)
                                <input type="hidden" id="dekan_faculty_id" value="{{ $dekanFacultyId }}">
                            @endif
                        </div>
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Ta'lim</label>
                            <select id="talim" class="select2" style="width: 100%;">
                                <option value="all" selected>Barchasi</option>
                                <option value="oddiy">Kunduzgi (oddiy)</option>
                                <option value="qoshma">Qo'shma ta'lim</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Variant (bo'linish)</label>
                            <select id="variant" class="select2" style="width: 100%;">
                                <option value="auto" selected>Avtomatik (1-3 kurs a,b · 4+ a,b,c)</option>
                                <option value="full">Guruh (to'liq)</option>
                                <option value="ab">a,b guruhchalar</option>
                                <option value="abc">a,b,c guruhchalar</option>
                                <option value="all">Barcha variantlar (Excel)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Me'yorlar (chegaralar) -->
                    <div class="filter-row" style="align-items:flex-end;">
                        <div class="norm-group" style="flex:1 1 auto;">
                            <span class="norm-title">Kurs me'yorlari — oqim va guruhcha (kursni o'chirib qo'ysangiz u optimizatsiya qilinmaydi)</span>
                            <div class="kn-row">
                                @for($k = 1; $k <= 6; $k++)
                                    <div class="kn-card" data-kurs="{{ $k }}">
                                        <label class="kn-head">
                                            <input type="checkbox" class="kn-on" checked>
                                            {{ $k }}-kurs
                                        </label>
                                        <div class="kn-line" title="Oqim me'yori (max va tolerantlik)">
                                            <span class="kn-lbl">oqim</span>
                                            <input type="number" class="kn-omax norm-in kn-in" value="120" min="1">
                                            <span class="kn-pm">±</span>
                                            <input type="number" class="kn-otol norm-in kn-sm" value="5" min="0">
                                        </div>
                                        <div class="kn-line" title="Guruhcha me'yori (max va tolerantlik)">
                                            <span class="kn-lbl">grch</span>
                                            <input type="number" class="kn-smax norm-in kn-in" value="{{ $k <= 3 ? 15 : 10 }}" min="1">
                                            <span class="kn-pm">±</span>
                                            <input type="number" class="kn-stol norm-in kn-sm" value="0" min="0">
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                        <div class="norm-group" title="Fakultetlar ALOHIDA qoladi (har birining o'z dekani bor). Bir xil yo'nalishli fakultetlar (masalan 1↔2-son davolash) kam to'lgan oqimlari qo'shni fakultet guruhlari bilan to'ldiriladi. Faqat optimizatsiyalangan holatga qo'llanadi.">
                            <span class="norm-title">Fakultetlararo</span>
                            <label class="ff-toggle">
                                <input type="checkbox" id="merge_faculties">
                                <span class="ff-slider"></span>
                                <span class="ff-state"></span>
                            </label>
                        </div>
                        <div class="norm-group" title="Kelasi o'quv yili uchun rejalashtirilgan oqim: joriy talabalar +1 kursga suriladi, yangi 1-kurs bashoratdan (Bo'lajak kontingent) qo'shiladi. Joriy tasdiqlangan holatga tegmaydi — alohida saqlanadi.">
                            <span class="norm-title">Kelasi yil (reja)</span>
                            <label class="ff-toggle">
                                <input type="checkbox" id="projection">
                                <span class="ff-slider"></span>
                                <span class="ff-state"></span>
                            </label>
                            <select id="projection_year" class="select2" style="width:130px;margin-top:4px;display:none;">
                                @foreach($projectionYears as $py)
                                    <option value="{{ $py }}">{{ $py }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 420px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="btn-calculate" class="btn-calc" onclick="openGoalModal()" title="Joriy holat va optimizatsiya taklifini hisoblash (avval maqsad so'raladi)">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Hisoblash
                                </button>
                                <button type="button" id="btn-excel" class="btn-excel" onclick="downloadExcel()" disabled>
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Excel
                                </button>
                                <a href="{{ route('admin.reports.oqim.overrides') }}" class="btn-fix" title="Aralash tilli / xato guruhlarni qo'lda to'g'rilash">
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Guruh tuzatish
                                </a>
                                <button type="button" class="btn-fix" style="color:#7c3aed;border-color:#ddd6fe;" onclick="openHistory()" title="Tasdiqlangan oqimlar tarixi (real va reja)">
                                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Tarix
                                </button>
                            </div>
                        </div>
                    </div>
                    <p style="margin:2px 2px 0;font-size:11.5px;color:#64748b;">
                        Faqat faol (o'qiyotgan) talabalar hisobga olinadi. <b>Oqim</b> — ma'ruzaga birga boradigan guruhlar
                        (til bo'yicha alohida, talaba soni oqim me'yoridan oshmaydi). Me'yorlar <b>har kurs uchun alohida</b> belgilanadi
                        (guruhcha — yagona tushuncha: 1-3 kurs a,b; 4-6 kurs a,b,c bo'linadi, o'lchami kurs sozlamasidan olinadi);
                        kurs katagi o'chirilsa — u kurs optimizatsiya qilinmaydi (joriy holatida qoladi). <b>Joriy holat</b> — HEMISdagidek, tasdiqlanadigan holat (o'zgarmaydi);
                        <b>Taklif etilayotgan o'zgartirish</b> — nimani nimaga o'zgartirish (kamayadigan guruh/oqimlar); <b>Optimizatsiyadan keyingi holat</b> — o'zgarishlardan keyingi to'liq holat (tasdiqlangach joriy holatga aylanadi).
                        <b>Fakultetlararo oqim optimizatsiyasi</b> yoqilsa — fakultetlar alohida qoladi, faqat bir fakultetning kam to'lgan oqimi qo'shni fakultet oqimiga ko'chiriladi (mehmon guruhlar belgilanadi).
                        Har xil tildagi guruhlar bir oqim/guruhga qo'shilmaydi.
                    </p>
                </div>

                <!-- Bo'lajak kontingent (yangi 1-kurs) — faqat rejalashtirilgan rejimda -->
                <div id="contingent-panel" style="display:none;margin:12px 20px 0;border:1px solid #c7d2fe;border-radius:10px;background:#f5f7ff;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;padding:10px 14px;border-bottom:1px solid #e0e7ff;">
                        <div style="font-weight:800;color:#3730a3;font-size:13px;">🎓 Yangi 1-kurs bashorati (yangi qabul)</div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <button type="button" id="ct-copy-all" class="af-btn" style="background:#e0e7ff;color:#3730a3;border:none;border-radius:7px;padding:5px 10px;font-size:12px;font-weight:700;cursor:pointer;">↺ Joriy 1-kursdan nusxa (hammasi)</button>
                            <button type="button" id="ct-save" class="af-btn af-approve" onclick="saveContingent()" style="padding:5px 12px;">💾 Saqlash</button>
                            <button type="button" id="btn-promote" onclick="promoteApproved()" style="background:#7c3aed;color:#fff;border:none;border-radius:7px;padding:5px 12px;font-size:12px;font-weight:700;cursor:pointer;" title="Tasdiqlangan joriy oqimni +1 kursga o'tkazadi (2-6 kurs qo'lda tuzatilgani saqlanadi), yangi 1-kurs bashoratdan. To'g'ridan-to'g'ri tahrirlash rejimida ochiladi.">↗ Tasdiqlangan oqimni o'tkazish</button>
                        </div>
                    </div>
                    <div style="padding:6px 14px 4px;font-size:11.5px;color:#6366f1;">
                        2-6 kurslar joriy talabalardan avtomatik olinadi — bu yerda faqat yangi qabul (1-kurs) kiritiladi.
                        "Nusxa" — 2-kursga o'tayotgan joriy 1-kurs sonini yangi qabulga ko'chiradi.
                        Ro'yxatda yo'q yangi yo'nalish (masalan Oliy hamshiralik) uchun pastdan qo'shing.
                    </div>
                    <div id="ct-body" style="padding:8px 14px 4px;max-height:280px;overflow:auto;"></div>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:8px 14px 12px;border-top:1px dashed #c7d2fe;">
                        <span style="font-size:11.5px;font-weight:700;color:#3730a3;">➕ Yangi yo'nalish:</span>
                        <input id="ct-new-name" placeholder="Nomi (Oliy hamshiralik ishi)" style="border:1px solid #c7d2fe;border-radius:6px;padding:3px 7px;font-size:12px;width:200px;">
                        <input id="ct-new-code" placeholder="Kodi" style="border:1px solid #c7d2fe;border-radius:6px;padding:3px 7px;font-size:12px;width:90px;">
                        <select id="ct-new-fac" style="border:1px solid #c7d2fe;border-radius:6px;padding:3px 7px;font-size:12px;"></select>
                        <select id="ct-new-lang" style="border:1px solid #c7d2fe;border-radius:6px;padding:3px 7px;font-size:12px;" title="Ta'lim tili">
                            <option value="uz">o'z</option><option value="rus">rus</option><option value="ing">ing</option>
                        </select>
                        <input id="ct-new-cnt" type="number" min="0" placeholder="Soni" style="border:1px solid #c7d2fe;border-radius:6px;padding:3px 7px;font-size:12px;width:70px;">
                        <button type="button" id="ct-add" style="background:#4f46e5;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:12px;font-weight:700;cursor:pointer;">Qo'shish</button>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="result-area">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2.83-4M7 12a3 3 0 10-2.83-4"/>
                        </svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Hisoblash" tugmasini bosing</p>
                    </div>
                    <div id="loading-state" style="display:none;padding:60px 20px;text-align:center;">
                        <div class="spinner"></div>
                        <p style="color:#2b5ea7;font-size:14px;margin-top:16px;font-weight:600;">Hisoblanmoqda...</p>
                    </div>
                    <div id="table-area" style="display:none;">
                        <div style="padding:8px 20px 0;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <button type="button" class="oq-tab active" data-tab="joriy" onclick="switchTab('joriy')" title="HEMISdagi haqiqiy, tasdiqlanadigan holat — optimizatsiya bunga ta'sir qilmaydi">Joriy holat</button>
                            <button type="button" class="oq-tab" data-tab="taklif" onclick="switchTab('taklif')" title="Nimani nimaga o'zgartirish taklifi — kamayadigan guruh/oqimlar">
                                Taklif etilayotgan o'zgartirish <span id="opt-tab-badge" class="opt-tab-badge" style="display:none;"></span>
                            </button>
                            <button type="button" class="oq-tab" data-tab="after" onclick="switchTab('after')" title="Optimizatsiya qo'llangandan keyingi to'liq holat">
                                Optimizatsiyadan keyingi holat
                            </button>
                            <span id="time-badge" style="font-size:12px;color:#94a3b8;margin-left:auto;"></span>
                        </div>

                        <!-- JORIY tab -->
                        <div id="tab-joriy">
                            <div style="padding:8px 20px;background:#eff6ff;border-bottom:1px solid #bfdbfe;">
                                <span id="total-badge" class="badge" style="background:#2b5ea7;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                            </div>
                            <div id="report-body" style="padding:16px 20px;max-height:calc(100vh - 340px);overflow:auto;"></div>
                        </div>

                        <!-- TAKLIF ETILAYOTGAN O'ZGARTIRISH tab (solishtirma) -->
                        <div id="tab-taklif" style="display:none;">
                            <div id="opt-summary" class="opt-summary"></div>
                            <div id="opt-compare" style="padding:4px 20px 16px;max-height:calc(100vh - 360px);overflow:auto;"></div>
                        </div>

                        <!-- OPTIMIZATSIYADAN KEYINGI HOLAT tab (to'liq layout) -->
                        <div id="tab-after" style="display:none;">
                            <div style="padding:8px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <span id="after-total-badge" class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                                <span id="after-merge-note" style="display:none;font-size:12px;font-weight:600;color:#166534;">Fakultetlar alohida — kam to'lgan oqimlar qo'shni fakultet oqimiga ko'chirildi (mehmon guruhlar belgilangan).</span>
                                <span id="snap-badge" style="display:none;font-size:12px;font-weight:700;padding:4px 12px;border-radius:999px;"></span>
                            </div>
                            <div id="after-actions" style="display:none;padding:8px 20px;background:#fbfdff;border-bottom:1px solid #e2e8f0;align-items:center;gap:8px;flex-wrap:wrap;">
                                <button type="button" id="btn-edit" class="af-btn af-edit" onclick="toggleEdit()">✎ Qo'lda tahrirlash</button>
                                <button type="button" id="btn-load-snap" class="af-btn af-load" style="display:none;" onclick="loadSnapshot()">↺ Saqlangan holatni yuklash</button>
                                <span id="edit-hint" style="display:none;font-size:11.5px;color:#64748b;">Talaba sonini o'zgartiring — jami avtomatik yangilanadi. Bir guruhdan kamaytirib, boshqasiga qo'shing.</span>
                                <span style="margin-left:auto;display:inline-flex;gap:8px;">
                                    <button type="button" id="btn-save-draft" class="af-btn af-draft" onclick="saveSnapshot('draft')">💾 Qoralama saqlash</button>
                                    <button type="button" id="btn-approve" class="af-btn af-approve" onclick="saveSnapshot('approve')">✓ Tasdiqlash</button>
                                    <button type="button" id="btn-unapprove" class="af-btn af-unapprove" style="display:none;" onclick="saveSnapshot('unapprove')">Tasdiqni bekor qilish</button>
                                </span>
                                <span id="snap-status" style="font-size:12px;font-weight:700;"></span>
                            </div>
                            <div id="opt-body" style="padding:16px 20px;max-height:calc(100vh - 420px);overflow:auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimizatsiya maqsadi dialogi -->
    <div id="goal-overlay">
        <div id="goal-dialog">
            <div class="goal-head">
                <div>
                    <div class="goal-title">Optimizatsiya maqsadi</div>
                    <div class="goal-sub">Me'yorlar bir-biriga zid kelganda qaysi maqsad ustuvor bo'lsin?</div>
                </div>
                <button type="button" class="goal-x" onclick="closeGoalModal()">×</button>
            </div>
            <div class="goal-body">
                <label class="goal-opt">
                    <input type="radio" name="oqim_goal" value="fill">
                    <span class="goal-txt"><b>Oqim va guruhchalarni kamaytirish</b>
                    <small>Oqim me'yori (max ±) ustuvor. Kam to'lgan oqimlar to'ldiriladi, ortiqcha guruhchalar BUTUNICHA tarqatiladi — talabalari har xil guruh/oqimlarga +1 tadan beriladi (guruhcha 10 → 11 bo'lishi mumkin, bitta joyga to'planmaydi). Hech bir oqim limitdan oshmaydi.</small></span>
                </label>
                <label class="goal-opt">
                    <input type="radio" name="oqim_goal" value="balance">
                    <span class="goal-txt"><b>Teng taqsimlash</b>
                    <small>Kam to'lgan oqimlar teng bo'linadi — kichik qoldiq oqim qolmaydi, lekin ba'zi oqimlar me'yordan kamroq to'ladi. Hech bir oqim limitdan oshmaydi.</small></span>
                </label>
                <label class="goal-opt">
                    <input type="radio" name="oqim_goal" value="integrity">
                    <span class="goal-txt"><b>Guruhchalar butunligi</b>
                    <small>a,b / a,b,c guruhcha me'yorlari (15/10 ±) qat'iy — guruhchalar kattalashmaydi. Oqimlar guruhlarni butunicha ko'chirish bilan to'ldiriladi; kichik qoldiq oqim qolishi mumkin.</small></span>
                </label>
            </div>
            <div class="goal-foot">
                <button type="button" class="goal-cancel" onclick="closeGoalModal()">Bekor qilish</button>
                <button type="button" class="goal-go" onclick="confirmGoal()">
                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Hisoblash
                </button>
            </div>
        </div>
    </div>

    <!-- Tasdiqlangan oqimlar tarixi -->
    <div id="history-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:9999;">
        <div style="position:absolute;top:4%;left:50%;transform:translateX(-50%);width:92%;max-width:1100px;max-height:90vh;background:#fff;border-radius:12px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #e2e8f0;">
                <div style="font-weight:800;color:#1e293b;">📋 Tasdiqlangan oqimlar tarixi</div>
                <button type="button" onclick="closeHistory()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;">×</button>
            </div>
            <div style="display:flex;gap:8px;padding:10px 20px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;align-items:center;">
                <select id="hist-kind" style="border:1px solid #cbd5e1;border-radius:6px;padding:4px 8px;font-size:13px;">
                    <option value="">Barchasi (real + reja)</option>
                    <option value="real">Faqat real</option>
                    <option value="plan">Faqat reja (kelasi yil)</option>
                </select>
                <input id="hist-year" placeholder="O'quv yili (masalan 2026-2027)" style="border:1px solid #cbd5e1;border-radius:6px;padding:4px 8px;font-size:13px;width:200px;">
                <button type="button" onclick="loadHistory()" style="background:#2b5ea7;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:700;cursor:pointer;">Filtrlash</button>
                <button type="button" id="hist-export" onclick="exportHistory()" style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:700;cursor:pointer;">⬇ Excel</button>
                <span id="hist-back" style="display:none;margin-left:auto;"><button type="button" onclick="historyList()" style="background:#e2e8f0;border:none;border-radius:6px;padding:5px 12px;font-size:13px;cursor:pointer;">← Ro'yxatga qaytish</button></span>
            </div>
            <div id="hist-body" style="padding:12px 20px;overflow:auto;flex:1;"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function esc(s) { return $('<span>').text(s === null || s === undefined ? '' : s).html(); }

        var activeTab = 'joriy';
        var CAN_APPROVE = {{ ($canApprove ?? false) ? 'true' : 'false' }};
        var SNAP_SAVE_URL = '{{ route("admin.reports.oqim.snapshot.save") }}';
        var SNAP_SHOW_URL = '{{ route("admin.reports.oqim.snapshot.show") }}';
        var CONTINGENT_URL = '{{ route("admin.reports.oqim.contingent") }}';
        var CONTINGENT_SAVE_URL = '{{ route("admin.reports.oqim.contingent.save") }}';
        var DATA_URL = '{{ route("admin.reports.oqim.data") }}';
        var CSRF = '{{ csrf_token() }}';
        var afterState = [];   // optimizatsiyadan keyingi holat (tahrirlanadigan) — saqlash uchun
        var editMode = false;
        var currentGoal = localStorage.getItem('oqim_goal') || 'fill';

        function openGoalModal() {
            $('input[name="oqim_goal"][value="' + currentGoal + '"]').prop('checked', true);
            $('#goal-overlay').css('display', 'flex');
        }
        function closeGoalModal() { $('#goal-overlay').hide(); }
        function confirmGoal() {
            currentGoal = $('input[name="oqim_goal"]:checked').val() || 'fill';
            localStorage.setItem('oqim_goal', currentGoal);
            closeGoalModal();
            loadReport();
        }

        function getFilters(optimize) {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            // Kurs bo'yicha me'yorlar: {1: {on, oqim_max, oqim_tol, sub_max, sub_tol}, ...}
            var kurs = {};
            $('.kn-card').each(function() {
                var k = $(this).data('kurs');
                kurs[k] = {
                    on: $(this).find('.kn-on').is(':checked') ? 1 : 0,
                    oqim_max: $(this).find('.kn-omax').val() || 120,
                    oqim_tol: $(this).find('.kn-otol').val() || 0,
                    sub_max: $(this).find('.kn-smax').val() || (k <= 3 ? 15 : 10),
                    sub_tol: $(this).find('.kn-stol').val() || 0,
                };
            });
            var f = {
                education_type: $('#education_type').val() || '',
                faculty: dekanFaculty ? dekanFaculty.value : ($('#faculty').val() || ''),
                talim: $('#talim').val() || 'all',
                variant: $('#variant').val() || 'auto',
                kurs: kurs,
                // Reja (kurs o'tishi) ikkala holatga ham ta'sir qiladi — yagona farq shu
                optimize: optimize ? 1 : 0,
            };
            // Kelasi yil (rejalashtirilgan) rejim — joriy va optimizatsiya so'rovlariga ham qo'llanadi
            if ($('#projection').is(':checked')) {
                f.projection = 1;
                f.academic_year = $('#projection_year').val() || '';
            }
            // Fakultetlararo optimizatsiya va maqsad FAQAT optimizatsiyalangan holatga
            // qo'llanadi — joriy (tasdiqlangan) holat hech qachon o'zgarmaydi.
            if (optimize) {
                f.merge_faculties = $('#merge_faculties').is(':checked') ? 1 : 0;
                f.goal = currentGoal;
            }
            return f;
        }

        function switchTab(tab) {
            activeTab = tab;
            $('.oq-tab').removeClass('active');
            $('.oq-tab[data-tab="' + tab + '"]').addClass('active');
            $('#tab-joriy').toggle(tab === 'joriy');
            $('#tab-taklif').toggle(tab === 'taklif');
            $('#tab-after').toggle(tab === 'after');
        }

        function loadReport() {
            var url = '{{ route("admin.reports.oqim.data") }}';
            $('#empty-state').hide();
            $('#table-area').hide();
            $('#loading-state').show();
            $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');
            $('#btn-excel').prop('disabled', true).css('opacity', '0.5');

            var startTime = performance.now();
            // Ikkala holatni parallel yuklaymiz: joriy va optimizatsiya
            $.when(
                $.get(url, getFilters(false)),
                $.get(url, getFilters(true))
            ).done(function(r0, r1) {
                var joriy = r0[0], opt = r1[0];
                var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                $('#loading-state').hide();
                $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                if (!joriy.blocks || !joriy.blocks.length) {
                    $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi. Filtrlarni o'zgartirib ko'ring.");
                    return;
                }
                renderReport(joriy);
                renderOptimized(opt);
                renderComparison(opt.plan);
                $('#time-badge').text(elapsed + ' soniyada hisoblandi · ' + esc(joriy.generated_at));
                switchTab('joriy');
                $('#table-area').show();
                $('#btn-excel').prop('disabled', false).css('opacity', '1');
            }).fail(function(xhr) {
                $('#loading-state').hide();
                $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                if (xhr.responseJSON && xhr.responseJSON.error) msg += ' (' + xhr.responseJSON.error + ')';
                else if (xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                $('#empty-state').show().find('p:first').text(msg);
            });
        }

        // Bloklar layoutini chizadi (joriy va optimizatsiyalangan holat uchun umumiy).
        // editable=true bo'lsa talaba soni katakchalari tahrirlanadigan (input) bo'ladi.
        // Talabalarning umumiy sonini qaytaradi.
        function renderBlocks(blocks, bodySel, editable) {
            blocks = blocks || [];
            var grand = 0;
            var html = '<div class="lang-legend">Til: <span class="ll lang-uz">o\'z</span> <span class="ll lang-rus">rus</span> <span class="ll lang-ing">ing</span></div>';
            for (var b = 0; b < blocks.length; b++) {
                var block = blocks[b];
                html += '<div class="oqim-block">';
                html += '<div class="oqim-block-title">' + esc(block.title) + '</div>';
                html += '<div class="oqim-courses">';
                for (var c = 0; c < block.courses.length; c++) {
                    var course = block.courses[c];
                    grand += course.total;
                    // Kurs kesimida oqim va guruhcha soni
                    var oqN = (course.oqims || []).length, subN = 0;
                    for (var oi = 0; oi < oqN; oi++) { subN += (course.oqims[oi].rows || []).length; }
                    html += '<div class="oqim-course">';
                    html += '<table class="oqim-table"><thead><tr><th colspan="3">' + esc(course.level_name)
                         + ' <span class="crs-stats">' + oqN + ' oqim · ' + subN + ' grch</span></th></tr></thead><tbody>';
                    for (var o = 0; o < course.oqims.length; o++) {
                        var oq = course.oqims[o];
                        var lc = 'lang-' + (oq.lang || 'uz');
                        for (var r = 0; r < oq.rows.length; r++) {
                            var first = (r === 0), last = (r === oq.rows.length - 1);
                            var row = oq.rows[r];
                            html += '<tr class="' + lc + (first ? ' oq-first' : '') + (last ? ' oq-last' : '') + (row.visitor ? ' oq-visitor' : '') + '">';
                            if (first) {
                                var labelCell = editable
                                    ? '<input class="label-in" value="' + esc(oq.label) + '" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" style="width:66px;font-weight:700;font-size:11px;border:1px solid #cbd5e1;border-radius:4px;padding:1px 3px;">'
                                    : esc(oq.label);
                                html += '<td class="oq-label" rowspan="' + oq.rows.length + '">' + labelCell + '<span class="oq-sum" data-oqt="' + b + '-' + c + '-' + o + '">' + esc(oq.total) + ' ta</span>' + (oq.has_visitor ? '<span class="oq-mix">fakultetlararo</span>' : '') + '</td>';
                            }
                            if (editable) {
                                var rl = row.lang || oq.lang || 'uz';
                                var langSel = '<select class="lang-in" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" data-r="' + r + '" style="font-size:10px;border:1px solid #cbd5e1;border-radius:4px;padding:1px 2px;margin-left:3px;">'
                                    + '<option value="uz"' + (rl === 'uz' ? ' selected' : '') + '>o\'z</option>'
                                    + '<option value="rus"' + (rl === 'rus' ? ' selected' : '') + '>rus</option>'
                                    + '<option value="ing"' + (rl === 'ing' ? ' selected' : '') + '>ing</option></select>';
                                html += '<td class="oq-grp"><input class="grp-in" value="' + esc(row.name) + '" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" data-r="' + r + '" style="width:120px;font-size:11px;border:1px solid #cbd5e1;border-radius:4px;padding:1px 4px;">' + langSel + '</td>';
                            } else {
                                html += '<td class="oq-grp">' + esc(row.name)
                                     + (row.visitor ? ' <span class="oq-from">← ' + esc(row.from) + '</span>' : '') + '</td>';
                            }
                            if (editable) {
                                html += '<td class="oq-cnt"><input class="cnt-in" type="number" min="0" value="' + esc(row.count) + '" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" data-r="' + r + '"></td>';
                            } else {
                                html += '<td class="oq-cnt">' + esc(row.count) + '</td>';
                            }
                            html += '</tr>';
                        }
                    }
                    html += '<tr class="oq-total"><td colspan="2">Jami</td><td class="oq-cnt oq-crt" data-crt="' + b + '-' + c + '">' + esc(course.total) + '</td></tr>';
                    html += '</tbody></table>';
                    html += '</div>';
                }
                html += '</div></div>';
            }
            $(bodySel).html(html);
            return grand;
        }

        function rejaPrefix() {
            return $('#projection').is(':checked') ? 'REJA (kelasi yil) · ' : '';
        }

        function renderReport(res) {
            var grand = renderBlocks(res.blocks, '#report-body', false);
            var variantLabel = $('#variant option:selected').text();
            $('#total-badge').text(rejaPrefix() + 'Jami talaba: ' + grand + ' ta · ' + variantLabel.split('(')[0].trim());
        }

        function afterVariantLabel() {
            return $('#variant option:selected').text().split('(')[0].trim();
        }

        function renderAfterBody() {
            var grand = renderBlocks(afterState, '#opt-body', editMode);
            $('#after-total-badge').text(rejaPrefix() + 'Jami talaba: ' + grand + ' ta · ' + afterVariantLabel());
        }

        // Optimizatsiyadan keyingi holat — to'liq layout. Tahrirlash/tasdiqlash bloklari.
        function renderOptimized(res) {
            afterState = res.blocks || [];
            editMode = false;
            renderAfterBody();
            var hasX = res.plan && res.plan.xmoves && res.plan.xmoves.length;
            $('#after-merge-note').toggle(!!($('#merge_faculties').is(':checked') && hasX));

            // Tasdiqlash/tahrirlash paneli — faqat ruxsatli rollar uchun
            $('#after-actions').css('display', CAN_APPROVE ? 'flex' : 'none');
            $('#btn-edit').text('✎ Qo\'lda tahrirlash').removeClass('on');
            $('#edit-hint').hide();
            $('#snap-status').text('');

            // Saqlangan/tasdiqlangan holat belgisi
            var snap = res.snapshot;
            var $b = $('#snap-badge');
            if (snap) {
                if (snap.status === 'approved') {
                    $b.css({display:'inline-block', background:'#dcfce7', color:'#166534', border:'1px solid #86efac'})
                      .text('✓ Tasdiqlangan' + (snap.approved_at ? ' · ' + snap.approver + ' · ' + snap.approved_at : ''));
                    $('#btn-unapprove').show();
                } else {
                    $b.css({display:'inline-block', background:'#fef9c3', color:'#854d0e', border:'1px solid #fde68a'})
                      .text('Qoralama saqlangan · ' + (snap.updated_at || ''));
                    $('#btn-unapprove').hide();
                }
                $('#btn-load-snap').toggle(!!snap.has_data);
            } else {
                $b.hide();
                $('#btn-load-snap').hide();
                $('#btn-unapprove').hide();
            }
        }

        function toggleEdit() {
            editMode = !editMode;
            $('#btn-edit').toggleClass('on', editMode).text(editMode ? '✎ Tahrirlash yoqilgan' : '✎ Qo\'lda tahrirlash');
            $('#edit-hint').toggle(editMode);
            renderAfterBody();
        }

        // Guruh nomini tahrirlash
        $(document).on('input', '#opt-body .grp-in', function() {
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o'), r = +$(this).data('r');
            afterState[b].courses[c].oqims[o].rows[r].name = this.value;
        });
        // Oqim nomini (label) tahrirlash
        $(document).on('input', '#opt-body .label-in', function() {
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o');
            afterState[b].courses[c].oqims[o].label = this.value;
        });
        // Guruh tilini o'zgartirish — nomdagi til qavsini ham yangilaydi
        $(document).on('change', '#opt-body .lang-in', function() {
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o'), r = +$(this).data('r');
            var lg = this.value;
            var row = afterState[b].courses[c].oqims[o].rows[r];
            row.lang = lg;
            var sfx = { uz: " (o'z)", rus: ' (rus)', ing: ' (ing)' }[lg] || '';
            row.name = String(row.name).replace(/\s*\((?:o['’‘]?z|oz|uz|rus|ru|ing|eng|ang)\s*\)\s*$/i, '') + sfx;
            renderAfterBody();
        });

        // Talaba sonini tahrirlaganda — jami (oqim/kurs/umumiy) avtomatik yangilanadi.
        $(document).on('input', '#opt-body .cnt-in', function() {
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o'), r = +$(this).data('r');
            var v = parseInt(this.value, 10); if (isNaN(v) || v < 0) v = 0;
            afterState[b].courses[c].oqims[o].rows[r].count = v;
            var ot = afterState[b].courses[c].oqims[o].rows.reduce(function(s, x){ return s + (+x.count || 0); }, 0);
            afterState[b].courses[c].oqims[o].total = ot;
            $('#opt-body .oq-sum[data-oqt="' + b + '-' + c + '-' + o + '"]').text(ot + ' ta');
            var ct = afterState[b].courses[c].oqims.reduce(function(s, q){ return s + (+q.total || 0); }, 0);
            afterState[b].courses[c].total = ct;
            $('#opt-body .oq-crt[data-crt="' + b + '-' + c + '"]').text(ct);
            var grand = 0;
            for (var i = 0; i < afterState.length; i++) for (var j = 0; j < afterState[i].courses.length; j++) grand += (+afterState[i].courses[j].total || 0);
            $('#after-total-badge').text('Jami talaba: ' + grand + ' ta · ' + afterVariantLabel());
        });

        function saveSnapshot(action) {
            var $status = $('#snap-status');
            $status.css('color', '#94a3b8').text('saqlanmoqda...');
            $.ajax({
                url: SNAP_SAVE_URL, method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF },
                contentType: 'application/json',
                data: JSON.stringify({ action: action, context: getFilters(true), data: afterState, note: '' })
            }).done(function(res) {
                $status.css('color', '#16a34a').text('✓ ' + (action === 'approve' ? 'Tasdiqlandi' : (action === 'unapprove' ? 'Tasdiq bekor qilindi' : 'Saqlandi')));
                var $b = $('#snap-badge');
                if (res.status === 'approved') {
                    $b.css({display:'inline-block', background:'#dcfce7', color:'#166534', border:'1px solid #86efac'})
                      .text('✓ Tasdiqlangan' + (res.approved_at ? ' · ' + (res.approver || '') + ' · ' + res.approved_at : ''));
                    $('#btn-unapprove').show();
                    $('#btn-load-snap').show();
                } else {
                    $b.css({display:'inline-block', background:'#fef9c3', color:'#854d0e', border:'1px solid #fde68a'})
                      .text('Qoralama saqlangan');
                    $('#btn-unapprove').hide();
                    $('#btn-load-snap').show();
                }
            }).fail(function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Xatolik';
                $status.css('color', '#dc2626').text(msg);
            });
        }

        function loadSnapshot() {
            $.get(SNAP_SHOW_URL, getFilters(true)).done(function(res) {
                if (res && res.found && res.data) {
                    afterState = res.data;
                    editMode = false;
                    renderAfterBody();
                    $('#snap-status').css('color', '#2b5ea7').text('Saqlangan holat yuklandi');
                }
            });
        }

        function statBox(cur, opt, label) {
            var reduce = (cur || 0) - (opt || 0);
            var cls = reduce > 0 ? 'ok' : 'neutral';
            return '<div class="opt-stat ' + cls + '">'
                 + '<div class="opt-stat-num">' + (cur || 0) + ' → ' + (opt || 0) + '</div>'
                 + '<div class="opt-stat-lbl">' + label + (reduce > 0 ? ' <b>(−' + reduce + ')</b>' : '') + '</div></div>';
        }

        function renderComparison(plan) {
            plan = plan || {};
            var s = '';
            s += statBox(plan.cur_base, plan.opt_base, 'Akademik guruhlar');
            s += statBox(plan.cur_subgroups, plan.opt_subgroups, 'Kichik guruhchalar');
            s += statBox(plan.cur_oqim, plan.opt_oqim, 'Oqimlar');
            $('#opt-summary').html(s);

            var moves = plan.moves || [];
            var xmoves = plan.xmoves || [];
            var xbmoves = plan.xbmoves || [];
            var reduceSub = (plan.cur_subgroups || 0) - (plan.opt_subgroups || 0);
            var reduceOqim = (plan.cur_oqim || 0) - (plan.opt_oqim || 0);
            var badgeN = reduceOqim > 0 ? reduceOqim : reduceSub;
            $('#opt-tab-badge').toggle(badgeN > 0).text(badgeN > 0 ? '−' + badgeN : '');

            var m = '';

            // Fakultetlararo chala guruhlarni to'ldirish (a,b + yolg'iz a -> to'liq a,b,c)
            if (xbmoves.length) {
                m += '<div class="opt-moves-title">Fakultetlararo guruh to\'ldirish — ' + xbmoves.length + ' ta joyda chala guruhlar birlashtirilib to\'liq (a,b,c) qilinadi:</div>';
                for (var bi = 0; bi < xbmoves.length; bi++) {
                    var bm = xbmoves[bi];
                    var fl = (bm.from || []).map(function(n){ return esc(n); }).join(' + ');
                    m += '<div class="cmp-card">';
                    m += '<div class="cmp-head"><span class="cmp-title">' + esc(bm.course) + ' · ' + esc(bm.lang) + ' til</span>'
                       + '<span class="cmp-count">→ ' + esc(bm.to_fac) + '</span></div>';
                    m += '<div class="xmove-body">Chala guruhlar <b>' + fl + '</b> (jami <b>' + esc(bm.total) + ' ta</b>) birlashtirilib, '
                       + '<b>' + esc(bm.to_fac) + '</b> da <b>' + esc(bm.new_bases) + ' ta to\'liq guruh</b> qilinadi — guruhcha soni oshmaydi.';
                    if (bm.new_list && bm.new_list.length) {
                        m += '<div class="xm-detail">Yangi guruhlar tarkibi:';
                        for (var ni = 0; ni < bm.new_list.length; ni++) {
                            var nl = bm.new_list[ni];
                            m += '<div class="xm-line">• <b>' + esc(nl.name) + '</b> (' + esc(nl.count) + ' ta): ' + esc((nl.subs || []).join(', ')) + '</div>';
                        }
                        m += '</div>';
                    }
                    m += '</div></div>';
                }
            }

            // Fakultetlararo oqim to'ldirish (fakultetlar alohida qoladi)
            if (xmoves.length) {
                var oqRed = (plan.cur_oqim || 0) - (plan.opt_oqim || 0);
                m += '<div class="opt-moves-title">Fakultetlararo oqim to\'ldirish — kam to\'lgan oqimlar qo\'shni fakultet guruhlari bilan me\'yorgacha to\'ldiriladi' + (oqRed > 0 ? ' (jami −' + oqRed + ' oqim)' : '') + ':</div>';
                for (var xi = 0; xi < xmoves.length; xi++) {
                    var xm = xmoves[xi];
                    var gl = (xm.moved || []).map(function(g){ return esc(g.name) + ' (' + esc(g.count) + ')'; }).join(', ');
                    m += '<div class="cmp-card">';
                    m += '<div class="cmp-head"><span class="cmp-title">' + esc(xm.course) + ' · ' + esc(xm.lang) + ' til</span>'
                       + '<span class="cmp-count">' + esc(xm.from_fac) + ' → ' + esc(xm.to_fac) + '</span></div>';
                    if (xm.balanced) {
                        m += '<div class="xmove-body">Fakultetlar balansi uchun <b>butun oqim</b> (' + esc(xm.oqim_rows || (xm.moved || []).length) + ' guruhcha, <b>' + esc(xm.moved_total) + ' ta</b>) '
                           + '<b>' + esc(xm.from_fac) + '</b> → <b>' + esc(xm.to_fac) + '</b> ga o\'tkaziladi (' + esc(xm.to_before) + ' → <b>' + esc(xm.to_after) + '</b> ta) — talabalar soni tenglashadi.'
                           + '<div class="xm-detail">Ko\'chirilayotgan guruhlar: ' + (xm.moved || []).map(function(g){ return esc(g.name) + ' (' + esc(g.count) + ')'; }).join(', ') + '</div></div>';
                    } else if (xm.leveled) {
                        var rt = (xm.result_totals || []).join(', ');
                        m += '<div class="xmove-body">Kam to\'lgan oqimlar guruhlari <b>teng taqsimlanadi</b> — jami <b>' + esc(xm.moved_total) + ' ta</b> talaba '
                           + (xm.result_totals ? xm.result_totals.length : '') + ' ta oqimga teng bo\'linadi'
                           + (rt ? ' (natija: <b>' + esc(rt) + '</b> ta)' : '') + '.'
                           + '<div class="xm-detail">Qatnashgan guruhlar: ' + gl + '</div></div>';
                    } else if (xm.distributed) {
                        m += '<div class="xmove-body"><b>' + esc(xm.from_fac) + '</b> tarqatiladi — <b>[' + esc(xm.moved_total) + ' ta]</b> talaba boshqa guruhchalarga +1 tadan beriladi (guruhchalar biroz kattalashadi):';
                        var mv2 = xm.moved || [];
                        m += '<div class="xm-detail">';
                        for (var di = 0; di < mv2.length; di++) {
                            var dd = mv2[di];
                            var rc = (dd.recipients || []).map(function(r){ return esc(r.name) + ' <b>+' + esc(r.added) + '</b>'; }).join(', ');
                            m += '<div class="xm-line">• <b>' + esc(dd.name) + '</b> (' + esc(dd.count) + ' ta) → ' + (rc || 'tarqatildi') + '</div>';
                        }
                        m += '</div></div>';
                    } else {
                        m += '<div class="xmove-body"><b>' + esc(xm.from_fac) + '</b> dagi ' + gl + ' <b>[' + esc(xm.moved_total) + ' ta]</b> '
                           + '→ <b>' + esc(xm.to_fac) + '</b> oqimiga qo\'shiladi (' + esc(xm.to_before) + ' → <b>' + esc(xm.to_after) + '</b> ta).'
                           + ((xm.to_groups && xm.to_groups.length) ? '<div class="xm-detail">Qabul qiluvchi oqimning o\'z guruhlari: ' + xm.to_groups.map(function(n){ return esc(n); }).join(', ') + '</div>' : '')
                           + '</div>';
                    }
                    m += '</div>';
                }
            }

            if (!moves.length && !xmoves.length && !xbmoves.length) {
                m = '<div class="opt-empty">✓ Joriy taqsimot allaqachon me\'yorga mos — birlashtiriladigan (kam to\'lgan) guruh/oqim topilmadi.<br>'
                  + '<span style="font-weight:500;font-size:12.5px;color:#64748b;">Ko\'proq zichlash uchun me\'yor (max) yoki tolerantlik (±) ni oshiring; fakultetlararo ko\'chirish uchun tegishli katakchani belgilang.</span></div>';
            } else if (moves.length) {
                m += '<div class="opt-moves-title">Kichik guruhlarni zichlash — ' + moves.length + ' ta joyda kichik guruh kamaytiriladi (qizil = o\'chiriladi):</div>';
                for (var i = 0; i < moves.length; i++) {
                    var mv = moves[i];
                    var cur = mv.cur_subs || [], nw = mv.new_subs || [];

                    m += '<div class="cmp-card">';
                    m += '<div class="cmp-head"><span class="cmp-title">' + esc(mv.course) + ' · ' + esc(mv.lang) + ' til</span>'
                       + '<span class="cmp-meta">' + esc(mv.block) + '</span>'
                       + '<span class="cmp-count">' + mv.cur_sub_n + ' → ' + mv.new_sub_n + ' guruhcha · ' + mv.from + '→' + mv.to + ' guruh</span></div>';

                    m += '<table class="cmp-table"><thead><tr>'
                       + '<th>Joriy versiya (' + mv.cur_sub_n + ' guruhcha)</th><th style="width:32px;"></th><th>Yangi versiya (' + mv.new_sub_n + ' guruhcha)</th>'
                       + '</tr></thead><tbody>';

                    var rows = Math.max(cur.length, nw.length);
                    for (var r = 0; r < rows; r++) {
                        m += '<tr>';
                        // Joriy — yangidan ortiqcha qatorlar (oxiridan) o'chiriladi
                        if (r < cur.length) {
                            var isDrop = r >= nw.length;
                            m += '<td class="cmp-cell ' + (isDrop ? 'cmp-drop' : '') + '">'
                               + esc(cur[r].name) + ' <span class="cmp-num">' + esc(cur[r].count) + ' ta</span>'
                               + (isDrop ? ' <span class="cmp-x">o\'chiriladi</span>' : '') + '</td>';
                        } else { m += '<td></td>'; }
                        m += '<td class="cmp-arrow">' + (r === 0 ? '→' : '') + '</td>';
                        // Yangi
                        if (r < nw.length) {
                            m += '<td class="cmp-cell cmp-new">' + esc(nw[r].name)
                               + ' <span class="cmp-num">' + esc(nw[r].count) + ' ta</span></td>';
                        } else { m += '<td></td>'; }
                        m += '</tr>';
                    }
                    m += '</tbody></table>';
                    var elim = cur.length - nw.length;
                    if (elim > 0) {
                        m += '<div class="cmp-note">Oxirgi <b>' + elim + ' ta</b> kichik guruh o\'chirilib, talabalari yuqoridagi guruhchalarga teng taqsimlanadi'
                           + ((mv.dropped && mv.dropped.length) ? ' (guruh o\'chadi: <b>' + mv.dropped.join(', ') + '</b>)' : '') + '.</div>';
                    }
                    m += '</div>';
                }
            }
            $('#opt-compare').html(m);
        }

        function downloadExcel() {
            // Faol vkladka bo'yicha Excelга yuklaymiz: "joriy" — joriy holat; aks holda
            // (taklif / optimizatsiyadan keyingi holat) — optimizatsiyalangan holat.
            var params = getFilters(activeTab !== 'joriy');
            var query = $.param(params);
            window.location.href = '{{ route("admin.reports.oqim.export") }}?' + query;
        }

        // ===== Bo'lajak kontingent (yangi 1-kurs) =====
        var CT_ROWS = [];
        function loadContingent() {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            var p = {
                academic_year: $('#projection_year').val() || '',
                education_type_code: $('#education_type').val() || '',
                department_id: dekanFaculty ? dekanFaculty.value : ($('#faculty').val() || ''),
            };
            ctFillFaculties();
            $('#ct-body').html('<div style="color:#94a3b8;font-size:12px;">Yuklanmoqda...</div>');
            $.get(CONTINGENT_URL, p).done(function(res){
                // Faqat 1-kurs (yangi qabul) qatorlari
                CT_ROWS = (res.rows || []).filter(function(r){ return r.course === 1; });
                renderContingent();
            }).fail(function(){ $('#ct-body').html('<div style="color:#dc2626;font-size:12px;">Xatolik.</div>'); });
        }
        function ctLangs(r) { return r.langs || { uz: (r.projected || 0), rus: 0, ing: 0 }; }
        function ctSum(r) { var L = ctLangs(r); return (+L.uz || 0) + (+L.rus || 0) + (+L.ing || 0); }
        function renderContingent() {
            if (!CT_ROWS.length) { $('#ct-body').html('<div style="color:#94a3b8;font-size:12px;">Yo\'nalish topilmadi. (Ta\'lim turi/fakultetni tanlang)</div>'); return; }
            var h = '<table style="width:100%;border-collapse:collapse;font-size:12.5px;"><thead><tr style="color:#64748b;text-align:left;">' +
                '<th style="padding:3px 6px;">Yo\'nalish</th><th style="padding:3px 6px;text-align:right;">Joriy 1-kurs</th>' +
                '<th style="padding:3px 6px;text-align:center;color:#1d4ed8;">o\'z</th>' +
                '<th style="padding:3px 6px;text-align:center;color:#be123c;">rus</th>' +
                '<th style="padding:3px 6px;text-align:center;color:#6d28d9;">ing</th>' +
                '<th style="padding:3px 6px;text-align:right;">Jami</th><th></th></tr></thead><tbody>';
            CT_ROWS.forEach(function(r, i){
                var newBadge = r.department_id ? ' <span style="background:#e0e7ff;color:#4f46e5;font-size:9px;font-weight:700;padding:1px 5px;border-radius:6px;">yangi · ' + esc(r.department_name || '') + '</span>' : '';
                var L = ctLangs(r);
                function inp(lg, col){ return '<input type="number" min="0" value="' + (+L[lg] || 0) + '" data-i="' + i + '" data-lg="' + lg + '" class="ct-in" style="width:54px;text-align:right;border:1px solid ' + col + ';border-radius:6px;padding:2px 5px;">'; }
                h += '<tr style="border-top:1px solid #e0e7ff;">' +
                    '<td style="padding:3px 6px;">' + esc(r.specialty_name || r.specialty_code) + ' <span style="color:#a5b4fc;font-size:10px;">' + esc(r.specialty_code) + '</span>' + newBadge + '</td>' +
                    '<td style="padding:3px 6px;text-align:right;color:#64748b;">' + (r.current_first || 0) + '</td>' +
                    '<td style="padding:3px 6px;text-align:center;">' + inp('uz', '#bfdbfe') + '</td>' +
                    '<td style="padding:3px 6px;text-align:center;">' + inp('rus', '#fecdd3') + '</td>' +
                    '<td style="padding:3px 6px;text-align:center;">' + inp('ing', '#ddd6fe') + '</td>' +
                    '<td style="padding:3px 6px;text-align:right;font-weight:700;color:#3730a3;" class="ct-sum" data-i="' + i + '">' + ctSum(r) + '</td>' +
                    '<td style="padding:3px 6px;"><button type="button" class="ct-copy" data-i="' + i + '" style="background:none;border:none;color:#6366f1;cursor:pointer;font-size:11px;" title="Joriy 1-kursning til taqsimotidan nusxa">↺ nusxa</button></td>' +
                    '</tr>';
            });
            h += '</tbody></table>';
            $('#ct-body').html(h);
            $('.ct-in').on('input', function(){
                var i = +$(this).data('i'), lg = $(this).data('lg');
                if (!CT_ROWS[i].langs) CT_ROWS[i].langs = { uz:0, rus:0, ing:0 };
                CT_ROWS[i].langs[lg] = parseInt(this.value) || 0;
                CT_ROWS[i].projected = ctSum(CT_ROWS[i]);
                $('.ct-sum[data-i="' + i + '"]').text(CT_ROWS[i].projected);
            });
            $('.ct-copy').on('click', function(){ var i = +$(this).data('i'); CT_ROWS[i].langs = Object.assign({uz:0,rus:0,ing:0}, CT_ROWS[i].cur_langs || {}); CT_ROWS[i].projected = ctSum(CT_ROWS[i]); renderContingent(); });
        }
        function saveContingent() {
            // Har yo'nalish × til uchun alohida yozuv (0 ham saqlanadi — o'chirishni aks ettirish uchun)
            var items = [];
            CT_ROWS.forEach(function(r){
                var L = ctLangs(r);
                ['uz','rus','ing'].forEach(function(lg){
                    items.push({
                        specialty_code: String(r.specialty_code), specialty_name: r.specialty_name ? String(r.specialty_name) : null,
                        level_code: String(r.level_code), lang: lg,
                        department_id: r.department_id || null, department_name: r.department_name || null,
                        expected_count: parseInt(L[lg]) || 0
                    });
                });
            });
            if (!items.length) return;
            var btn = $('#ct-save').prop('disabled', true).text('...');
            $.ajax({ url: CONTINGENT_SAVE_URL, method: 'POST', contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': CSRF },
                data: JSON.stringify({ academic_year: $('#projection_year').val() || '', items: items })
            }).done(function(){ btn.text('✓ Saqlandi'); setTimeout(function(){ btn.prop('disabled', false).text('💾 Saqlash'); }, 1500); })
              .fail(function(xhr){
                  var msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || ('HTTP ' + xhr.status);
                  btn.prop('disabled', false).text('Xato: ' + msg);
                  console.error('Kontingent saqlash xatosi:', xhr.status, xhr.responseText);
              });
        }
        // Yangi yo'nalish qo'shish (fakultet ro'yxatini asosiy filtrdan olamiz)
        function ctFillFaculties() {
            var $sel = $('#ct-new-fac'); if ($sel.children().length) return;
            $('#faculty option').each(function(){
                if (!this.value) return;
                $sel.append('<option value="' + this.value + '">' + esc($(this).text()) + '</option>');
            });
        }
        $(document).on('click', '#ct-add', function(){
            var name = $('#ct-new-name').val().trim();
            var code = $('#ct-new-code').val().trim();
            var facId = $('#ct-new-fac').val();
            var facName = $('#ct-new-fac option:selected').text();
            var lang = $('#ct-new-lang').val() || 'uz';
            var cnt = parseInt($('#ct-new-cnt').val()) || 0;
            if (!name || !code) { alert("Yo'nalish nomi va kodini kiriting."); return; }
            // Mavjud bo'lsa — tanlangan tilga qo'shamiz
            var ex = CT_ROWS.find(function(r){ return r.specialty_code === code; });
            if (ex) { if (!ex.langs) ex.langs = {uz:0,rus:0,ing:0}; ex.langs[lang] = cnt; ex.projected = ctSum(ex); ex.department_id = facId; ex.department_name = facName; }
            else { var L = {uz:0,rus:0,ing:0}; L[lang] = cnt; CT_ROWS.push({ specialty_code: code, specialty_name: name, level_code: '11', course: 1,
                current_first: 0, langs: L, cur_langs: {uz:0,rus:0,ing:0}, projected: cnt, department_id: facId, department_name: facName, is_new: true }); }
            $('#ct-new-name,#ct-new-code,#ct-new-cnt').val('');
            renderContingent();
        });

        // ===== Tasdiqlangan oqimni kelasi yilga o'tkazish =====
        function ctLevelNum(course) {
            var m = (course.level_name || '').match(/(\d+)/); if (m) return +m[1];
            var n = parseInt(course.level_code) || 0; return n > 6 ? n - 10 : n;
        }
        // Tasdiqlangan bloklarni +1 kursga suramiz (6-kursdan oshsa — bitiradi, tushadi)
        function promoteBlocks(blocks) {
            var out = JSON.parse(JSON.stringify(blocks || []));
            out.forEach(function(bl) {
                bl.courses = (bl.courses || []).map(function(co) {
                    var num = ctLevelNum(co) + 1;
                    if (num > 6) return null;
                    co.level_name = num + '-kurs';
                    co.level_code = String(10 + num);
                    return co;
                }).filter(Boolean);
            });
            return out.filter(function(bl) { return bl.courses.length; });
        }
        // 2-6 kurslarni tasdiqlangan (surilgan) holat bilan almashtiramiz
        function overlayApproved(base, promoted) {
            var map = {};
            promoted.forEach(function(bl) { bl.courses.forEach(function(co) { map[bl.title + '|' + co.level_code] = co; }); });
            base.forEach(function(bl) {
                bl.courses.forEach(function(co) {
                    if (ctLevelNum(co) >= 2 && map[bl.title + '|' + co.level_code]) {
                        var ap = map[bl.title + '|' + co.level_code];
                        co.oqims = ap.oqims; co.total = ap.total;
                    }
                });
            });
        }
        function promoteApproved() {
            if (!$('#projection').is(':checked')) { alert('Avval "Kelasi yil (reja)" ni yoqing va bashoratni saqlang.'); return; }
            $('#empty-state').hide(); $('#table-area').hide(); $('#loading-state').show();
            var pf = getFilters(false); // projection yoqilgan, optimize=0 — asos (1-kurs + surilgan 2-6)
            $.get(DATA_URL, pf).done(function(base) {
                var blocks = base.blocks || [];
                // Tasdiqlangan JORIY oqim (projection'siz kontekst)
                var cf = getFilters(true); delete cf.projection; delete cf.academic_year;
                $.get(SNAP_SHOW_URL, cf).done(function(snap) {
                    var used = false;
                    if (snap && snap.found && snap.data && snap.data.length) {
                        overlayApproved(blocks, promoteBlocks(snap.data));
                        used = true;
                    }
                    $('#loading-state').hide();
                    afterState = blocks; editMode = true;
                    $('#table-area').show(); switchTab('after'); renderAfterBody();
                    $('#after-actions').css('display', CAN_APPROVE ? 'flex' : 'none');
                    $('#btn-edit').addClass('on').text('✎ Tahrirlash yoqilgan'); $('#edit-hint').show();
                    $('#snap-badge').hide(); $('#btn-unapprove').hide(); $('#btn-load-snap').hide();
                    $('#snap-status').css('color', used ? '#166534' : '#b45309').text(
                        used ? "Tasdiqlangan oqim +1 kursga o'tkazildi (2-6 kurs). Yangi 1-kurs bashoratdan. Tahrirlang va tasdiqlang."
                             : "Tasdiqlangan joriy oqim topilmadi — to'liq hisoblangan holat ko'rsatildi. Tahrirlang va tasdiqlang.");
                    $('#btn-excel').prop('disabled', false).css('opacity', '1');
                }).fail(function() {
                    $('#loading-state').hide(); afterState = blocks; editMode = true;
                    $('#table-area').show(); switchTab('after'); renderAfterBody();
                });
            }).fail(function(xhr) {
                $('#loading-state').hide();
                $('#empty-state').show().find('p:first').text('Xatolik (HTTP ' + xhr.status + ')');
            });
        }

        // ===== Tasdiqlangan oqimlar tarixi =====
        var HISTORY_URL = '{{ route("admin.reports.oqim.history") }}';
        var HISTORY_SHOW_URL = '{{ url("admin/reports/oqim/history") }}';
        var HISTORY_EXPORT_URL = '{{ route("admin.reports.oqim.history.export") }}';
        var histViewId = null;
        function exportHistory() {
            var p = new URLSearchParams();
            if (histViewId) { p.set('id', histViewId); }
            else { if ($('#hist-kind').val()) p.set('kind', $('#hist-kind').val()); if ($('#hist-year').val()) p.set('academic_year', $('#hist-year').val()); }
            window.location = HISTORY_EXPORT_URL + '?' + p.toString();
        }
        function openHistory() { $('#history-overlay').css('display', 'block'); loadHistory(); }
        function closeHistory() { $('#history-overlay').hide(); }
        function historyList() { $('#hist-back').hide(); loadHistory(); }
        function loadHistory() {
            $('#hist-back').hide();
            histViewId = null;
            $('#hist-body').html('<div style="color:#94a3b8;">Yuklanmoqda...</div>');
            $.get(HISTORY_URL, { kind: $('#hist-kind').val(), academic_year: $('#hist-year').val() }).done(function(rows) {
                if (!rows.length) { $('#hist-body').html('<div style="color:#94a3b8;">Tasdiqlangan oqim topilmadi.</div>'); return; }
                var h = '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="color:#64748b;text-align:left;border-bottom:2px solid #e2e8f0;">' +
                    '<th style="padding:6px 8px;">Turi</th><th style="padding:6px 8px;">O\'quv yili</th><th style="padding:6px 8px;">Fakultet</th>' +
                    '<th style="padding:6px 8px;text-align:right;">Talaba</th><th style="padding:6px 8px;text-align:right;">Oqim</th><th style="padding:6px 8px;text-align:right;">Guruhcha</th>' +
                    '<th style="padding:6px 8px;">Tasdiqlangan</th><th style="padding:6px 8px;">Mas\'ul</th><th></th></tr></thead><tbody>';
                rows.forEach(function(r) {
                    var badge = r.kind === 'plan'
                        ? '<span style="background:#ede9fe;color:#6d28d9;font-weight:700;font-size:11px;padding:2px 8px;border-radius:8px;">Reja</span>'
                        : '<span style="background:#dcfce7;color:#166534;font-weight:700;font-size:11px;padding:2px 8px;border-radius:8px;">Real</span>';
                    var s = r.summary || {};
                    h += '<tr style="border-bottom:1px solid #f1f5f9;">' +
                        '<td style="padding:6px 8px;">' + badge + '</td>' +
                        '<td style="padding:6px 8px;">' + esc(r.academic_year || '—') + '</td>' +
                        '<td style="padding:6px 8px;">' + esc(r.faculty_name) + '</td>' +
                        '<td style="padding:6px 8px;text-align:right;">' + (s.students || 0) + '</td>' +
                        '<td style="padding:6px 8px;text-align:right;">' + (s.oqim || 0) + '</td>' +
                        '<td style="padding:6px 8px;text-align:right;">' + (s.guruhcha || 0) + '</td>' +
                        '<td style="padding:6px 8px;">' + esc(r.approved_at || '') + '</td>' +
                        '<td style="padding:6px 8px;">' + esc(r.approver || '') + '</td>' +
                        '<td style="padding:6px 8px;"><button type="button" onclick="viewHistory(' + r.id + ')" style="background:#2b5ea7;color:#fff;border:none;border-radius:6px;padding:3px 10px;font-size:12px;cursor:pointer;">Ko\'rish</button></td>' +
                        (r.note ? '</tr><tr><td colspan="9" style="padding:0 8px 6px;color:#94a3b8;font-size:11.5px;">Izoh: ' + esc(r.note) + '</td>' : '') +
                        '</tr>';
                });
                h += '</tbody></table>';
                $('#hist-body').html(h);
            }).fail(function() { $('#hist-body').html('<div style="color:#dc2626;">Xatolik.</div>'); });
        }
        function viewHistory(id) {
            histViewId = id;
            $('#hist-body').html('<div style="color:#94a3b8;">Yuklanmoqda...</div>');
            $.get(HISTORY_SHOW_URL + '/' + id).done(function(res) {
                $('#hist-back').show();
                var head = '<div style="margin-bottom:10px;font-weight:700;color:#1e293b;">' +
                    (res.kind === 'plan' ? 'Reja' : 'Real') + ' · ' + esc(res.academic_year || '') + ' · ' + esc(res.faculty_name) +
                    ' · ' + esc(res.approved_at || '') + ' · ' + esc(res.approver || '') + '</div>';
                $('#hist-body').html(head + '<div id="hist-view"></div>');
                renderBlocks(res.blocks || [], '#hist-view', false);
            }).fail(function() { $('#hist-body').html('<div style="color:#dc2626;">Xatolik.</div>'); });
        }

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', placeholder: $(this).find('option:first').text() });
            });

            // Kelasi yil (rejalashtirilgan) rejim: yil tanlovini ko'rsatish + banner + kontingent paneli
            function toggleProjection() {
                var on = $('#projection').is(':checked');
                $('#projection_year').toggle(on).next('.select2-container').toggle(on);
                if (on) {
                    if (!$('#projection-banner').length) {
                        $('#result-area').prepend(
                            '<div id="projection-banner" style="margin:12px 20px 0;padding:10px 14px;border-radius:8px;' +
                            'background:#fffbeb;border:1px solid #fcd34d;color:#92400e;font-size:13px;font-weight:600;line-height:1.5;">' +
                            '⏳ Rejalashtirilgan (kelasi yil) rejimi — <b>2-6 kurs</b> joriy talabalardan avtomatik +1 kursga suriladi. ' +
                            '<b>Yangi 1-kurs</b> bashoratini quyidagi panelda kiriting va saqlang, keyin "Hisoblash". ' +
                            'Bu holat joriy tasdiqqa tegmaydi.</div>');
                    }
                    $('#projection-banner').show();
                    $('#contingent-panel').show();
                    loadContingent();
                } else {
                    $('#projection-banner').hide();
                    $('#contingent-panel').hide();
                }
            }
            $('#projection').on('change', toggleProjection);
            $('#projection_year').on('change', function(){ if ($('#projection').is(':checked')) loadContingent(); });
            $('#education_type, #faculty').on('change', function(){ if ($('#projection').is(':checked')) loadContingent(); });
            $('#ct-copy-all').on('click', function(){
                CT_ROWS.forEach(function(r){ r.langs = Object.assign({uz:0,rus:0,ing:0}, r.cur_langs || {}); r.projected = ctSum(r); });
                renderContingent();
            });
            toggleProjection();
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; align-items: flex-end; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); transform: translateY(-1px); }
        .btn-opt { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: linear-gradient(135deg, #7c3aed, #a855f7); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(124,58,237,0.3); height: 36px; }
        .btn-opt:hover { background: linear-gradient(135deg, #6d28d9, #7c3aed); transform: translateY(-1px); }
        .btn-fix { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #fff; color: #b45309; border: 1px solid #fcd34d; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; height: 36px; }
        .btn-fix:hover { background: #fffbeb; border-color: #f59e0b; }

        .norm-group { background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:5px 10px 6px; }

        /* Kurs bo'yicha me'yorlar */
        .kn-row { display:flex; gap:8px; flex-wrap:wrap; }
        .kn-card { border:1px solid #e2e8f0; border-radius:8px; padding:4px 8px 6px; background:#fbfdff; transition:opacity .15s; }
        .kn-card:has(.kn-on:not(:checked)) { opacity:0.45; background:#f8fafc; }
        .kn-head { display:flex; align-items:center; gap:5px; font-size:11.5px; font-weight:800; color:#1e3a5f; cursor:pointer; margin-bottom:3px; }
        .kn-head input { width:13px; height:13px; cursor:pointer; accent-color:#2b5ea7; }
        .kn-line { display:flex; align-items:center; gap:3px; margin-top:2px; }
        .kn-lbl { font-size:10px; font-weight:700; color:#94a3b8; width:28px; }
        .kn-pm { font-size:10px; font-weight:700; color:#94a3b8; }
        .kn-in { width:46px; height:24px; font-size:11.5px; }
        .kn-sm { width:36px; height:24px; font-size:11.5px; }

        /* Fakultetlararo almashtirish — norm-group ichida ixcham toggle */
        .ff-toggle { display:inline-flex; align-items:center; gap:8px; cursor:pointer; user-select:none; height:28px; }
        .ff-toggle input { position:absolute; opacity:0; width:0; height:0; }
        .ff-slider { position:relative; flex:0 0 auto; width:36px; height:20px; background:#cbd5e1; border-radius:999px; transition:background .18s; }
        .ff-slider::before { content:''; position:absolute; top:2px; left:2px; width:16px; height:16px; background:#fff; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,0.25); transition:transform .18s; }
        .ff-toggle input:checked + .ff-slider { background:linear-gradient(135deg,#2b5ea7,#3b7ddb); }
        .ff-toggle input:checked + .ff-slider::before { transform:translateX(16px); }
        .ff-toggle input:focus-visible + .ff-slider { box-shadow:0 0 0 3px rgba(43,94,167,0.25); }
        .ff-state::after { content:"o'chiq"; font-size:11.5px; font-weight:700; color:#94a3b8; }
        .ff-toggle input:checked ~ .ff-state::after { content:"yoqilgan"; color:#1e4b8a; }

        /* Optimizatsiya maqsadi dialogi */
        #goal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:center; justify-content:center; padding:20px; }
        #goal-dialog { background:#fff; border-radius:14px; width:100%; max-width:520px; box-shadow:0 24px 60px rgba(0,0,0,0.35); overflow:hidden; }
        .goal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 18px; background:linear-gradient(135deg,#2b5ea7,#3b7ddb); color:#fff; }
        .goal-title { font-size:16px; font-weight:800; }
        .goal-sub { font-size:12px; opacity:0.9; margin-top:2px; }
        .goal-x { background:rgba(255,255,255,0.2); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:18px; line-height:1; cursor:pointer; flex-shrink:0; }
        .goal-x:hover { background:rgba(255,255,255,0.35); }
        .goal-body { padding:14px 18px; display:flex; flex-direction:column; gap:8px; }
        .goal-opt { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px; cursor:pointer; transition:border-color .12s, background .12s; }
        .goal-opt:hover { border-color:#93c5fd; background:#f8fbff; }
        .goal-opt:has(input:checked) { border-color:#2b5ea7; background:#eff6ff; box-shadow:0 0 0 2px rgba(43,94,167,0.12); }
        .goal-opt input { margin-top:3px; accent-color:#2b5ea7; flex-shrink:0; }
        .goal-txt { display:flex; flex-direction:column; gap:2px; }
        .goal-txt b { font-size:13.5px; color:#0f172a; }
        .goal-txt small { font-size:11.5px; color:#64748b; line-height:1.45; }
        .goal-foot { display:flex; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid #f1f5f9; background:#fbfdff; }
        .goal-cancel { padding:8px 16px; background:#fff; color:#64748b; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
        .goal-go { display:inline-flex; align-items:center; gap:7px; padding:8px 20px; background:linear-gradient(135deg,#2b5ea7,#3b7ddb); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
        .goal-go:hover { background:linear-gradient(135deg,#1e4b8a,#2b5ea7); }
        .norm-title { display:block; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.03em; color:#475569; margin-bottom:3px; }
        .norm-inputs { display:flex; gap:8px; }
        .norm-inputs > div { display:flex; align-items:center; gap:4px; }
        .norm-inputs label { font-size:11px; font-weight:700; color:#64748b; }
        .norm-in { width:60px; height:28px; padding:0 4px; border:1px solid #cbd5e1; border-radius:6px; text-align:center; font-size:12.5px; font-weight:600; color:#1e293b; -moz-appearance:textfield; }
        .norm-in::-webkit-outer-spin-button, .norm-in::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
        .norm-in:focus { outline:none; border-color:#2b5ea7; box-shadow:0 0 0 2px rgba(43,94,167,0.12); }
        .btn-excel { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-excel:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); transform: translateY(-1px); }
        .btn-excel:disabled { cursor: not-allowed; opacity: 0.5; }

        .spinner { width: 40px; height: 40px; margin: 0 auto; border: 4px solid #e2e8f0; border-top-color: #2b5ea7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; color: #1e293b; font-size: 0.8rem; font-weight: 500; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; }

        .oqim-block { margin-bottom: 26px; }
        .oqim-block-title { font-size: 14px; font-weight: 800; color: #0f172a; background: linear-gradient(135deg, #e8edf5, #dbe4ef); padding: 8px 12px; border-radius: 8px 8px 0 0; border: 1px solid #cbd5e1; }
        .oqim-courses { display: flex; gap: 10px; flex-wrap: nowrap; overflow-x: auto; padding: 10px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; background: #fbfdff; }
        .oqim-course { flex: 0 0 auto; }
        .oqim-table { border-collapse: collapse; font-size: 12px; min-width: 190px; }
        .oqim-table th { background: linear-gradient(135deg, #dbe4ef, #cbd7e8); color: #1e3a5f; font-weight: 700; padding: 6px 8px; text-align: center; border: 1px solid #b8c6dc; font-size: 12px; }
        .crs-stats { display: inline-block; margin-left: 6px; font-size: 10px; font-weight: 700; color: #64748b; background: rgba(255,255,255,0.65); border-radius: 999px; padding: 1px 8px; }
        .oqim-table td { border: 1px solid #e2e8f0; padding: 4px 8px; }
        .oq-label { text-align: center; font-weight: 700; color: #2b5ea7; background: #f0f6ff; white-space: nowrap; }
        .oq-sum { display: block; margin-top: 2px; font-size: 10.5px; font-weight: 700; color: #16a34a; }
        .oq-grp { color: #0f172a; white-space: nowrap; }
        .oq-cnt { text-align: center; font-weight: 600; color: #334155; width: 40px; }
        .oq-total td { background: #f1f5f9; font-weight: 800; color: #0f172a; text-align: center; }
        .badge { display: inline-block; font-weight: 600; }

        /* Oqimni o'qitish tili bo'yicha ranglash */
        .oqim-table td.oq-grp { border-left: 3px solid transparent; }
        .lang-uz  td.oq-grp { border-left-color:#3b82f6; }
        .lang-rus td.oq-grp { border-left-color:#f43f5e; }
        .lang-ing td.oq-grp { border-left-color:#8b5cf6; }
        .lang-uz  .oq-label { color:#1d4ed8; background:#eff6ff; }
        .lang-rus .oq-label { color:#be123c; background:#fff1f2; }
        .lang-ing .oq-label { color:#6d28d9; background:#f5f3ff; }
        .lang-rus td.oq-grp, .lang-rus td.oq-cnt { background:#fffafa; }
        .lang-ing td.oq-grp, .lang-ing td.oq-cnt { background:#fbfaff; }
        tr.oq-first td { border-top:2px solid #cbd5e1; }

        .lang-legend { font-size:12px; font-weight:700; color:#64748b; margin-bottom:10px; display:flex; align-items:center; gap:8px; }
        .lang-legend .ll { padding:2px 12px; border-radius:999px; font-weight:800; border-left:3px solid; }
        .lang-legend .ll.lang-uz  { color:#1d4ed8; background:#eff6ff; border-left-color:#3b82f6; }
        .lang-legend .ll.lang-rus { color:#be123c; background:#fff1f2; border-left-color:#f43f5e; }
        .lang-legend .ll.lang-ing { color:#6d28d9; background:#f5f3ff; border-left-color:#8b5cf6; }

        /* Fakultetlararo ko'chirilgan (mehmon) guruhlar — "Optimizatsiyadan keyingi holat" da */
        tr.oq-visitor td.oq-grp { background:#fff7ed; }
        tr.oq-visitor td.oq-cnt { background:#fff7ed; }
        .oq-from { display:inline-block; font-size:10px; font-weight:800; color:#c2410c; background:#ffedd5; border-radius:999px; padding:0 7px; margin-left:4px; }
        .oq-mix { display:block; margin-top:2px; font-size:9.5px; font-weight:800; color:#c2410c; }
        .xmove-body { padding:8px 12px; font-size:13px; color:#334155; line-height:1.5; background:#fffbeb; }
        .xm-detail { margin-top:6px; padding:7px 10px; background:#fff; border:1px solid #f1f5f9; border-radius:8px; font-size:12px; color:#475569; line-height:1.6; }
        .xm-line { padding:1px 0; }
        .xm-line b { color:#0f172a; }

        /* Tasdiqlash / qo'lda tahrirlash paneli */
        .af-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 13px; border-radius:8px; font-size:12.5px; font-weight:700; cursor:pointer; border:1px solid transparent; }
        .af-edit { background:#eef2ff; color:#4338ca; border-color:#c7d2fe; }
        .af-edit.on { background:#4338ca; color:#fff; }
        .af-load { background:#fff; color:#0369a1; border-color:#bae6fd; }
        .af-draft { background:#fff; color:#334155; border-color:#cbd5e1; }
        .af-approve { background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; }
        .af-approve:hover { background:linear-gradient(135deg,#15803d,#16a34a); }
        .af-unapprove { background:#fff; color:#dc2626; border-color:#fecaca; }
        .cnt-in { width:52px; height:26px; padding:0 4px; border:1px solid #cbd5e1; border-radius:6px; text-align:center; font-size:12.5px; font-weight:700; color:#0f172a; -moz-appearance:textfield; }
        .cnt-in::-webkit-outer-spin-button, .cnt-in::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
        .cnt-in:focus { outline:none; border-color:#4338ca; box-shadow:0 0 0 2px rgba(67,56,202,0.15); }

        #opt-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:center; justify-content:center; padding:20px; }
        #opt-dialog { background:#fff; border-radius:14px; width:100%; max-width:760px; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,0.35); overflow:hidden; }
        .opt-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:16px 20px; background:linear-gradient(135deg,#7c3aed,#a855f7); color:#fff; }
        .opt-title { font-size:17px; font-weight:800; }
        .opt-sub { font-size:12px; opacity:0.9; margin-top:2px; }
        .opt-x { background:rgba(255,255,255,0.2); border:none; color:#fff; width:30px; height:30px; border-radius:8px; font-size:20px; line-height:1; cursor:pointer; flex-shrink:0; }
        .opt-x:hover { background:rgba(255,255,255,0.35); }
        .oq-tab { padding:8px 16px; border:none; background:transparent; border-bottom:3px solid transparent; font-size:13px; font-weight:700; color:#64748b; cursor:pointer; margin-bottom:-1px; display:inline-flex; align-items:center; gap:6px; }
        .oq-tab:hover { color:#2b5ea7; }
        .oq-tab.active { color:#2b5ea7; border-bottom-color:#2b5ea7; }
        .opt-tab-badge { background:#16a34a; color:#fff; font-size:10.5px; font-weight:800; padding:1px 7px; border-radius:999px; }

        .cmp-card { border:1px solid #e2e8f0; border-radius:10px; margin-bottom:12px; overflow:hidden; }
        .cmp-head { display:flex; align-items:baseline; gap:12px; flex-wrap:wrap; padding:8px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
        .cmp-title { font-size:14px; font-weight:800; color:#7c3aed; }
        .cmp-meta { font-size:11px; color:#94a3b8; }
        .cmp-count { margin-left:auto; font-size:12px; font-weight:800; color:#16a34a; }
        .cmp-table { width:100%; border-collapse:collapse; font-size:13px; }
        .cmp-table th { text-align:left; padding:6px 12px; font-size:11px; text-transform:uppercase; font-weight:700; color:#64748b; background:#fff; border-bottom:1px solid #f1f5f9; }
        .cmp-table td { padding:5px 12px; border-bottom:1px solid #f8fafc; vertical-align:middle; }
        .cmp-cell { font-weight:700; color:#0f172a; }
        .cmp-num { font-weight:600; color:#64748b; font-size:12px; }
        .cmp-drop { color:#dc2626; text-decoration:line-through; background:#fef2f2; border-radius:6px; }
        .cmp-drop .cmp-num { color:#dc2626; }
        .cmp-x { text-decoration:none; font-size:10.5px; font-weight:800; color:#fff; background:#dc2626; padding:1px 7px; border-radius:999px; margin-left:4px; }
        .cmp-new { color:#166534; }
        .cmp-new .cmp-num { color:#16a34a; }
        .cmp-arrow { text-align:center; color:#94a3b8; font-weight:800; }
        .cmp-note { padding:7px 12px; font-size:12px; color:#475569; background:#fffbeb; border-top:1px solid #fde68a; }

        .opt-summary { display:flex; gap:12px; padding:16px 20px; flex-wrap:wrap; border-bottom:1px solid #f1f5f9; }
        .opt-stat { flex:1; min-width:150px; border-radius:10px; padding:12px 14px; border:1px solid #e2e8f0; }
        .opt-stat.ok { background:#f0fdf4; border-color:#bbf7d0; }
        .opt-stat.neutral { background:#f8fafc; }
        .opt-stat-num { font-size:22px; font-weight:800; color:#0f172a; }
        .opt-stat.ok .opt-stat-num { color:#16a34a; }
        .opt-stat-lbl { font-size:12px; color:#64748b; font-weight:600; margin-top:2px; }
        .opt-moves { padding:12px 20px; overflow-y:auto; }
        .opt-moves-title { font-size:13px; font-weight:800; color:#334155; margin-bottom:10px; }
        .opt-empty { padding:24px; text-align:center; color:#475569; font-size:13.5px; font-weight:600; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; }
        .opt-move { border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:8px; }
        .opt-move-h { display:flex; align-items:baseline; justify-content:space-between; gap:8px; flex-wrap:wrap; }
        .opt-move-base { font-size:14px; font-weight:800; color:#7c3aed; }
        .opt-move-meta { font-size:11px; color:#94a3b8; }
        .opt-move-b { display:flex; align-items:center; gap:8px; margin-top:6px; flex-wrap:wrap; }
        .opt-badge { font-size:12px; font-weight:700; padding:3px 10px; border-radius:6px; }
        .opt-badge.cur { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
        .opt-badge.opt { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
        .opt-arrow { color:#94a3b8; font-weight:800; }
        .opt-move-note { font-size:12px; color:#475569; margin-top:6px; line-height:1.45; }
        .opt-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 20px; border-top:1px solid #f1f5f9; background:#fbfdff; }
    </style>
</x-app-layout>
