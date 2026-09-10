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
                            <button type="button" class="oq-tab active" data-tab="approved" onclick="switchTab('approved')" title="Tasdiqlangan oqimlar — oxirgi tasdiqlangan holat va sanalar bo'yicha tarix">
                                ✓ Tasdiqlangan <span id="ap-tab-badge" class="ap-tab-badge" style="display:none;"></span>
                            </button>
                            <button type="button" class="oq-tab calc-only" data-tab="joriy" onclick="switchTab('joriy')" title="HEMISdagi haqiqiy, tasdiqlanadigan holat — optimizatsiya bunga ta'sir qilmaydi" style="display:none;">Joriy holat</button>
                            <button type="button" class="oq-tab calc-only" data-tab="taklif" onclick="switchTab('taklif')" title="Nimani nimaga o'zgartirish taklifi — kamayadigan guruh/oqimlar" style="display:none;">
                                Taklif etilayotgan o'zgartirish <span id="opt-tab-badge" class="opt-tab-badge" style="display:none;"></span>
                            </button>
                            <button type="button" class="oq-tab calc-only" data-tab="after" onclick="switchTab('after')" title="Optimizatsiya qo'llangandan keyingi to'liq holat" style="display:none;">
                                Optimizatsiyadan keyingi holat
                            </button>
                            <button type="button" class="oq-tab" data-tab="manual" onclick="switchTab('manual')" title="Guruhlarni oqimlar orasida drag & drop bilan qo'lda ko'chirish va HEMISdan guruh/talabalarni tortish">
                                🖐 Qo'lda tuzatish
                            </button>
                            <span id="time-badge" style="font-size:12px;color:#94a3b8;margin-left:auto;"></span>
                        </div>

                        <!-- TASDIQLANGAN tab (oxirgi tasdiqlangan holat + sanalar bo'yicha versiyalar) -->
                        <div id="tab-approved">
                            <div style="padding:8px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <label style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#166534;">Tasdiqlangan sana</label>
                                <select id="ap-version" onchange="selectVersion(this.value)" style="border:1px solid #86efac;border-radius:8px;padding:6px 10px;font-size:13px;font-weight:600;color:#14532d;background:#fff;min-width:320px;max-width:560px;">
                                    <option value="">Yuklanmoqda...</option>
                                </select>
                                <button type="button" class="af-btn af-load" onclick="loadApprovedList(false)" title="Ro'yxatni yangilash">↻</button>
                                <span id="ap-badge" class="badge" style="display:none;background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                                <span style="margin-left:auto;display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <button type="button" id="ap-edit" class="af-btn af-edit" onclick="editVersion()" style="display:none;" title="Shu versiyani drag & drop bilan qo'lda tuzatib, yangi sana bilan tasdiqlash">✎ Qo'lda tuzatish (shu versiyadan)</button>
                                    <button type="button" id="ap-excel" class="af-btn af-draft" onclick="exportVersionExcel()" style="display:none;" title="Shu versiyani Excel (jadval) ko'rinishida yuklash">⬇ Excel</button>
                                    <button type="button" class="af-btn af-draft" onclick="openHistory()" title="To'liq tarix ro'yxati">📋 Tarix</button>
                                </span>
                            </div>
                            <div id="ap-drafts" style="display:none;padding:8px 20px;background:#fffbeb;border-bottom:1px solid #fde68a;align-items:center;gap:10px;flex-wrap:wrap;">
                                <label style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#92400e;">💾 Saqlangan qoralamalar</label>
                                <select id="ap-draft" style="border:1px solid #fcd34d;border-radius:8px;padding:6px 10px;font-size:13px;font-weight:600;color:#78350f;background:#fff;min-width:320px;max-width:600px;">
                                    <option value="">—</option>
                                </select>
                                <button type="button" class="af-btn af-edit" onclick="openDraft()" title="Tanlangan qoralamani qo'lda tuzatish ekraniga yuklab, ishni davom ettirish">✎ Qoralamani ochish (davom ettirish)</button>
                                <span style="font-size:11.5px;color:#92400e;">Qoralama — tasdiqlanmagan ish; "✓ Tasdiqlash" bosilgach yuqoridagi sanalar ro'yxatiga tushadi.</span>
                            </div>
                            <div id="ap-note" style="display:none;padding:6px 20px;font-size:12px;color:#475569;background:#fbfdff;border-bottom:1px solid #e2e8f0;"></div>
                            <div id="ap-body" style="padding:16px 20px;max-height:calc(100vh - 340px);overflow:auto;"></div>
                        </div>

                        <!-- JORIY tab -->
                        <div id="tab-joriy" style="display:none;">
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

                        <!-- QO'LDA TUZATISH (drag & drop) tab -->
                        <div id="tab-manual" style="display:none;">
                            <div style="padding:8px 20px;background:#fdf4ff;border-bottom:1px solid #f0abfc;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <span id="mn-total-badge" class="badge" style="background:#a21caf;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;"></span>
                                <span style="font-size:11.5px;color:#86198f;">Guruhni ushlab (⠿) boshqa oqimga yoki "Yangi oqim" maydoniga tashlang — jami avtomatik yangilanadi. Talaba soni qo'lda kiritilmaydi — bazadan (HEMISdan) avtomatik olinadi. Boshqa fakultetga tashlansa, guruh "mehmon" deb belgilanadi.</span>
                            </div>
                            <div id="mn-actions" style="display:none;padding:8px 20px;background:#fbfdff;border-bottom:1px solid #e2e8f0;align-items:center;gap:8px;flex-wrap:wrap;">
                                <button type="button" id="mn-hemis-groups" class="af-btn af-load" onclick="hemisPull('groups')" title="HEMISdagi BARCHA faol guruhlarni (asl nomi bilan) tortib, fakultet → kurs → til bo'yicha oqimlarga joylaydi. Ekrandagi joriy joylashuv almashtiriladi (↶ Bekor qilish bilan qaytariladi)">⇩ Guruhlarni HEMISdan tortish (barchasi, fakultet/kurs/til bo'yicha)</button>
                                <button type="button" id="mn-hemis-students" class="af-btn af-load" onclick="hemisPull('students')" title="HEMISdan talabalarni yangilash (fon rejimida, uzoqroq davom etadi)">⇩ Talabalarni HEMISdan tortish</button>
                                <button type="button" id="mn-merge-new" class="af-btn af-load" onclick="mergeNewGroups()" title="Bazadan yangilash: yangi guruhlar qo'shiladi, talaba sonlari bazadagi songa yangilanadi, nofaol/yo'q guruhlar olib tashlanadi, takror bloklar birlashtiriladi — joylashuv o'zgarmaydi">⟳ Bazadan yangilash (yangi + sonlar, nofaollarni olib tashlash)</button>
                                <button type="button" id="mn-diag" class="af-btn" style="background:#fff;color:#0f766e;border-color:#99f6e4;" onclick="openDiagnose()" title="Tashxis: ekrandagi son ≠ bazadagi son bo'lgan guruhlar; har biri uchun HEMIS bilan jonli solishtirish va qayta tortish">🩺 Tashxis</button>
                                <span id="mn-hemis-status" style="font-size:11.5px;font-weight:600;"></span>
                                <span style="margin-left:auto;display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <button type="button" class="af-btn af-draft" onclick="manualSource('joriy')" title="Joriy (HEMISdagi) holatdan boshlab qo'lda tuzatish">⟲ Joriy holatdan</button>
                                    <button type="button" class="af-btn af-draft" onclick="manualSource('opt')" title="Optimizatsiyalangan holatdan boshlab qo'lda tuzatish">⟲ Optimizatsiyadan</button>
                                    <button type="button" class="af-btn af-load" onclick="loadSnapshot()" title="Oldin saqlangan/tasdiqlangan holatni yuklash">↺ Saqlangan holat</button>
                                    <button type="button" id="mn-del-inactive" class="af-btn af-unapprove" onclick="removeInactiveGroups()" title="Bazada (HEMISda) nofaol yoki yo'q guruhlarni ro'yxatdan o'chirish — avval ro'yxat ko'rsatiladi, tasdiqlasangiz o'chadi; bekor qilish mumkin">🗑 Nofaol/yo'q guruhlarni o'chirish</button>
                                    <button type="button" id="mn-del-empty" class="af-btn af-unapprove" onclick="removeEmptyGroups()" title="Talabasi yo'q (0) barcha guruhlarni ro'yxatdan o'chirish — bekor qilish mumkin">🗑 Bo'sh guruhlarni o'chirish</button>
                                    <button type="button" id="mn-undo" class="af-btn af-draft" onclick="manualUndo()" disabled>↶ Bekor qilish</button>
                                    <button type="button" class="af-btn af-draft" onclick="saveSnapshot('draft')">💾 Qoralama saqlash</button>
                                    <button type="button" class="af-btn af-approve" onclick="saveSnapshot('approve')">✓ Tasdiqlash</button>
                                    <span id="mn-save-status" style="font-size:12px;font-weight:700;"></span>
                                </span>
                            </div>
                            <div id="mn-body" style="padding:16px 20px;max-height:calc(100vh - 420px);overflow:auto;"></div>
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
                <button type="button" id="hist-table-export" onclick="exportHistoryTable()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:700;cursor:pointer;">▦ Jadval ko'rinishida yuklash</button>
                <button type="button" id="hist-export" onclick="exportHistory()" style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:13px;font-weight:700;cursor:pointer;">⬇ Excel (CSV)</button>
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
            $('#tab-approved').toggle(tab === 'approved');
            $('#tab-joriy').toggle(tab === 'joriy');
            $('#tab-taklif').toggle(tab === 'taklif');
            $('#tab-after').toggle(tab === 'after');
            $('#tab-manual').toggle(tab === 'manual');
            if (tab === 'manual') renderManual();
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
                // Qo'lda tuzatish vkladkasi uchun manba holatlarni saqlab qo'yamiz
                joriyState = JSON.parse(JSON.stringify(joriy.blocks || []));
                optState = JSON.parse(JSON.stringify(opt.blocks || []));
                calcGroupIds = joriy.group_ids || [];
                manualKnownIds = idSetFromList(calcGroupIds); // afterState = optimizatsiya — shu guruhlar hisobga olingan
                MN_UNDO = [];
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
                manualContext = null; // hisoblangan holat — joriy filtrlar konteksti
                $('#time-badge').text(elapsed + ' soniyada hisoblandi · ' + esc(joriy.generated_at));
                $('.calc-only').show();
                switchTab('joriy');
                $('#table-area').show();
                $('#btn-excel').prop('disabled', false).css('opacity', '1');
            }).fail(function(xhr) {
                $('#loading-state').hide();
                $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                var msg = "Xatolik yuz berdi. Qayta urinib ko'ring.";
                if (xhr.responseJSON && xhr.responseJSON.error) msg += ' (' + xhr.responseJSON.error + ')';
                else if (xhr.status) msg += ' (HTTP ' + xhr.status + ')';
                if (AP_LOADED) { $('#table-area').show(); switchTab('approved'); mnFlash(msg); }
                else $('#empty-state').show().find('p:first').text(msg);
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
            var $status = $('#snap-status, #mn-save-status');
            $status.css('color', '#94a3b8').text('saqlanmoqda...');
            $.ajax({
                url: SNAP_SAVE_URL, method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF },
                contentType: 'application/json',
                // Tarixdagi versiyadan tuzatilayotgan bo'lsa — o'sha versiyaning konteksti,
                // aks holda joriy filtrlar konteksti ostida saqlanadi.
                data: JSON.stringify({ action: action, context: manualContext || getFilters(true), data: afterState, note: '' })
            }).done(function(res) {
                $status.css('color', '#16a34a').text('✓ ' + (action === 'approve' ? 'Tasdiqlandi' + (res.approved_at ? ' · ' + res.approved_at : '') : (action === 'unapprove' ? 'Tasdiq bekor qilindi' : 'Saqlandi')));
                // Tasdiqlanganda tarixga yangi sana-vaqtli versiya yoziladi — ro'yxatni yangilab, uni ko'rsatamiz
                if (action === 'approve') loadApprovedList(true);
                loadDraftsList();
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
            // Tarixdagi versiya/qoralama ustida ishlanayotgan bo'lsa — o'sha kontekstning saqlangan holati
            $.get(SNAP_SHOW_URL, manualContext || getFilters(true)).done(function(res) {
                if (res && res.found && res.data) {
                    afterState = res.data;
                    manualKnownIds = idsFromBlocks(afterState);
                    editMode = false;
                    renderAfterBody();
                    if (activeTab === 'manual') { MN_UNDO = []; renderManual(); mergeNewGroups({ countsOnly: true, silent: true }); }
                    $('#snap-status, #mn-save-status').css('color', '#2b5ea7').text('Saqlangan holat yuklandi');
                } else if (activeTab === 'manual') {
                    mnFlash('Saqlangan holat topilmadi.');
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
                    manualContext = null; manualKnownIds = idSetFromList(base.group_ids || []); MN_UNDO = [];
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
                $('#empty-state').show().find('p:first').text(xhr.status === 419
                    ? 'Sessiya eskirgan. Sahifani yangilang (Ctrl+Shift+R) va qayta urinib ko\'ring.'
                    : ('Xatolik (HTTP ' + xhr.status + ')'));
            });
        }

        // ===== Qo'lda tuzatish (drag & drop) vkladkasi =====
        var HEMIS_PULL_URL = '{{ route("admin.reports.oqim.hemis.pull") }}';
        var joriyState = [];   // oxirgi hisoblangan joriy holat (manba sifatida)
        var optState = [];     // oxirgi hisoblangan optimizatsiyalangan holat (manba sifatida)
        var MN_UNDO = [];      // bekor qilish uchun holatlar to'plami
        var dragSrc = null;    // hozir sudralayotgan guruh manzili {b,c,o,r}

        var MN_LANG_LBL = { uz: "o'z", rus: 'rus', ing: 'ing' };

        function mnFlash(msg) {
            var $f = $('#mn-flash');
            if (!$f.length) $f = $('<div id="mn-flash"></div>').appendTo('body');
            $f.text(msg).stop(true, true).fadeIn(120).delay(2000).fadeOut(300);
        }

        // Barcha jami (oqim/kurs) qiymatlarini afterState bo'yicha qayta hisoblaydi
        function mnRecalc() {
            for (var b = 0; b < afterState.length; b++) {
                var courses = afterState[b].courses || [];
                for (var c = 0; c < courses.length; c++) {
                    var ct = 0;
                    var oqims = courses[c].oqims || [];
                    for (var o = 0; o < oqims.length; o++) {
                        var ot = (oqims[o].rows || []).reduce(function(s, r){ return s + (+r.count || 0); }, 0);
                        oqims[o].total = ot;
                        ct += ot;
                    }
                    courses[c].total = ct;
                }
            }
        }

        function renderManual() {
            $('#mn-actions').css('display', CAN_APPROVE ? 'flex' : 'none');
            // Hisoblash qilinmagan bo'lsa — ekrandagi tasdiqlangan versiya ustida ishlaymiz
            if ((!afterState || !afterState.length) && AP_CURRENT && AP_CURRENT.blocks && AP_CURRENT.blocks.length) {
                afterState = JSON.parse(JSON.stringify(AP_CURRENT.blocks));
                manualContext = normalizeManualContext(AP_CURRENT.context);
                manualKnownIds = idsFromBlocks(afterState);
                MN_UNDO = [];
                mnRecalc();
                renderAfterBody();
                $('#mn-save-status').css('color', '#166534').text('Tasdiqlangan versiya (' + (AP_CURRENT.approved_at || '') + ') yuklandi — tuzatib "✓ Tasdiqlash" bossangiz yangi sana bilan tarixga tushadi.');
                setTimeout(function() { mergeNewGroups({ countsOnly: true, silent: true }); }, 0); // sonlar bazadan avtomatik
            }
            if (!afterState || !afterState.length) {
                $('#mn-body').html('<div style="padding:48px 20px;text-align:center;color:#94a3b8;font-size:14px;font-weight:600;">' +
                    'Avval filtrlarni tanlab <b>"Hisoblash"</b> tugmasini bosing yoki <b>"✓ Tasdiqlangan"</b> vkladkasida versiya tanlang — natija shu yerda drag &amp; drop bilan tuzatish uchun ochiladi.</div>');
                $('#mn-total-badge').text('');
                return;
            }
            var grand = 0, html = '';
            for (var b = 0; b < afterState.length; b++) {
                var block = afterState[b];
                html += '<div class="mn-block"><div class="oqim-block-title">' + esc(block.title) + '</div><div class="mn-courses">';
                var courses = block.courses || [];
                for (var c = 0; c < courses.length; c++) {
                    var course = courses[c];
                    grand += (+course.total || 0);
                    var oqims = course.oqims || [];
                    var subN = 0;
                    for (var i = 0; i < oqims.length; i++) subN += (oqims[i].rows || []).length;
                    html += '<div class="mn-course" data-b="' + b + '" data-c="' + c + '" data-lvl="' + ctLevelNum(course) + '">';
                    html += '<div class="mn-course-head">' + esc(course.level_name)
                          + ' <span class="crs-stats">' + oqims.length + ' oqim · ' + subN + ' grch · <b data-mnct="' + b + '-' + c + '">' + esc(course.total) + '</b> ta</span></div>';
                    for (var o = 0; o < oqims.length; o++) {
                        var oq = oqims[o];
                        var rows = oq.rows || [];
                        var baseLang = rows.length ? (rows[0].lang || oq.lang || 'uz') : (oq.lang || 'uz');
                        var mixed = false;
                        for (var r = 0; r < rows.length; r++) {
                            if ((rows[r].lang || oq.lang || 'uz') !== baseLang) { mixed = true; break; }
                        }
                        html += '<div class="mn-oqim lang-' + esc(oq.lang || 'uz') + '" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '">';
                        html += '<div class="mn-oqim-head">'
                              + (CAN_APPROVE
                                    ? '<input class="mn-label" value="' + esc(oq.label) + '" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" title="Oqim nomi — tahrirlash mumkin">'
                                    : '<span class="mn-label-ro">' + esc(oq.label) + '</span>')
                              + (mixed ? '<span class="mn-mixed" title="Diqqat: bir oqimda har xil tildagi guruhlar bor!">⚠ aralash til</span>' : '')
                              + '<span class="mn-oqim-total" data-mnot="' + b + '-' + c + '-' + o + '">' + esc(oq.total) + ' ta</span>'
                              + (CAN_APPROVE ? '<button type="button" class="mn-x mn-x-oqim" title="Oqimni (barcha guruhlari bilan) ro\'yxatdan o\'chirish" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '">×</button>' : '')
                              + '</div>';
                        if (!rows.length) {
                            html += '<div class="mn-empty-hint">Bo\'sh oqim — guruhni shu yerga tashlang</div>';
                        }
                        for (var r2 = 0; r2 < rows.length; r2++) {
                            var row = rows[r2];
                            var rl = row.lang || oq.lang || 'uz';
                            html += '<div class="mn-row lang-' + esc(rl) + '" draggable="' + (CAN_APPROVE ? 'true' : 'false') + '"'
                                  + ' data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" data-r="' + r2 + '">'
                                  + '<span class="mn-handle" title="Ushlab suring">⠿</span>'
                                  + '<span class="mn-name' + (+row.gid > 0 ? ' mn-name-chk' : '') + '" title="' + esc(row.hemis_name || row.name) + (+row.gid > 0 ? ' — bosing: bazadagi talabalar ro\'yxati va tekshiruv' : '') + '"' + (+row.gid > 0 ? ' data-gid="' + (+row.gid) + '" data-cnt="' + esc(row.count) + '"' : '') + '>' + esc(row.hemis_name || row.name)
                                  + (row.visitor ? ' <span class="oq-from">← ' + esc(row.from || 'mehmon') + '</span>' : '')
                                  + (row._db === 'inactive' ? ' <span class="mn-dbflag mn-dbflag-inactive" title="Bazada (HEMISda) NOFAOL guruh' + (+row.gid > 0 ? ' #' + (+row.gid) : '') + '">nofaol</span>' : '')
                                  + (row._db === 'missing' ? ' <span class="mn-dbflag mn-dbflag-missing" title="Bazada bunday guruh yo\'q (bashorat/soxta yoki o\'chirilgan)">yo\'q</span>' : '') + '</span>'
                                  + '<span class="mn-cnt-ro" title="Talaba soni bazadan (HEMISdan) avtomatik olinadi' + ((+row.gid > 0 || (row.gids && row.gids.length)) ? '' : ' — bu qatorda HEMIS ID yo\'q, son yangilanmaydi') + '">' + esc(row.count)
                                  + ((+row.gid > 0 || (row.gids && row.gids.length)) ? '' : '<span class="mn-noid" title="HEMIS ID yo\'q — bashorat (soxta) guruh yoki eski yozuv; son avtomatik yangilanmaydi">?</span>') + '</span>'
                                  + '<span class="mn-lang mn-lang-' + esc(rl) + '">' + (MN_LANG_LBL[rl] || rl) + '</span>'
                                  + (CAN_APPROVE ? '<button type="button" class="mn-x mn-mv" title="Boshqa oqim/fakultetga ko\'chirish (ro\'yxatdan tanlab — uzoq masofa uchun)" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" data-r="' + r2 + '">⇢</button>' : '')
                                  + (CAN_APPROVE ? '<button type="button" class="mn-x mn-x-row" title="Guruhni ro\'yxatdan o\'chirish (masalan bashoratdagi soxta guruh)" data-b="' + b + '" data-c="' + c + '" data-o="' + o + '" data-r="' + r2 + '">×</button>' : '')
                                  + '</div>';
                        }
                        html += '</div>';
                    }
                    if (CAN_APPROVE) {
                        html += '<div class="mn-new" data-b="' + b + '" data-c="' + c + '" title="Bosing — bo\'sh oqim ochiladi; yoki guruhni shu yerga tashlang">＋ Yangi oqim — bosing yoki guruhni shu yerga tashlang</div>';
                    }
                    html += '</div>';
                }
                html += '</div></div>';
            }
            $('#mn-body').html(html);
            $('#mn-total-badge').text(rejaPrefix() + 'Jami talaba: ' + grand + ' ta · qo\'lda tuzatish');
            $('#mn-undo').prop('disabled', !MN_UNDO.length);
        }

        function mnPushUndo() {
            MN_UNDO.push(JSON.stringify(afterState));
            if (MN_UNDO.length > 30) MN_UNDO.shift();
            $('#mn-undo').prop('disabled', false);
        }

        function manualUndo() {
            if (!MN_UNDO.length) return;
            afterState = JSON.parse(MN_UNDO.pop());
            renderManual();
            renderAfterBody();
        }

        // Manba tanlash: joriy (HEMIS) yoki optimizatsiyalangan holatdan boshlash
        function manualSource(src) {
            var base = src === 'joriy' ? joriyState : optState;
            if (!base || !base.length) { mnFlash('Avval "Hisoblash" tugmasini bosing.'); return; }
            afterState = JSON.parse(JSON.stringify(base));
            manualContext = null;
            manualKnownIds = idSetFromList(calcGroupIds);
            MN_UNDO = [];
            mnRecalc();
            renderManual();
            renderAfterBody();
            mnFlash(src === 'joriy' ? "Joriy (HEMIS) holat yuklandi — endi qo'lda tuzating." : 'Optimizatsiyalangan holat yuklandi.');
        }

        // Guruhni bir oqimdan boshqasiga ko'chirish.
        // to === -1 bo'lsa yangi oqim ochiladi; tr — nishon qator (shu o'ringa qo'yiladi).
        function mnMove(src, tb, tc, to, tr) {
            var srcCourse = afterState[src.b].courses[src.c];
            var tgtCourse = afterState[tb].courses[tc];
            if (ctLevelNum(srcCourse) !== ctLevelNum(tgtCourse)) {
                mnFlash("Har xil kurslar orasida guruh ko'chirib bo'lmaydi.");
                return;
            }
            var sameOqim = (src.b === tb && src.c === tc && src.o === to);
            if (sameOqim && (tr === undefined || tr === src.r)) return;

            mnPushUndo();
            var row = srcCourse.oqims[src.o].rows.splice(src.r, 1)[0];
            if (src.b !== tb) {
                row.visitor = true;
                row.from = row.from || afterState[src.b].title;
            }
            if (to === -1) {
                tgtCourse.oqims.push({
                    label: 'Oqim-' + (tgtCourse.oqims.length + 1),
                    lang: row.lang || 'uz',
                    total: 0,
                    rows: [row]
                });
            } else {
                var rows = tgtCourse.oqims[to].rows;
                if (!rows.length) tgtCourse.oqims[to].lang = row.lang || tgtCourse.oqims[to].lang || 'uz'; // bo'sh oqim — birinchi guruh tilini oladi
                if (sameOqim && tr !== undefined && tr > src.r) tr--;
                if (tr === undefined || tr === null || tr > rows.length) rows.push(row);
                else rows.splice(tr, 0, row);
                if ((row.lang || 'uz') !== (tgtCourse.oqims[to].lang || 'uz')) {
                    mnFlash("Diqqat: guruh tili oqim tilidan farq qiladi — oqim \"aralash til\" deb belgilandi.");
                }
            }
            // Bo'shab qolgan oqimni olib tashlaymiz
            if (!srcCourse.oqims[src.o].rows.length) srcCourse.oqims.splice(src.o, 1);
            mnRecalc();
            renderManual();
            renderAfterBody();
        }

        // Drag & drop hodisalari
        $(document).on('dragstart', '#mn-body .mn-row', function(e) {
            if (!CAN_APPROVE) return false;
            dragSrc = { b: +$(this).data('b'), c: +$(this).data('c'), o: +$(this).data('o'), r: +$(this).data('r') };
            $(this).addClass('mn-dragging');
            var dt = e.originalEvent.dataTransfer;
            if (dt) { dt.effectAllowed = 'move'; try { dt.setData('text/plain', 'mn'); } catch (err) {} }
        });
        $(document).on('dragend', '#mn-body .mn-row', function() {
            $(this).removeClass('mn-dragging');
            $('#mn-body .mn-over').removeClass('mn-over');
            mnRemoveDropLine();
            dragSrc = null;
        });
        // Sudrab yurganda tushish joyi: kursor Y bo'yicha qat'iy indeks hisoblanadi va
        // o'sha joyda "shu yerga tushadi" ko'rsatkichi chiziladi. Drop ham aynan shu indeksga.
        var MN_PULL_NOTE = ''; // oxirgi HEMIS tortish xulosasi — yangilash xabari boshida saqlanadi
        var dropHint = null; // { b, c, o, idx }
        function mnRemoveDropLine() { $('#mn-body .mn-drop-line').remove(); dropHint = null; }
        function mnPlaceDropLine($oqim, b, c, o, clientY) {
            var idx = null, $before = null;
            $oqim.find('.mn-row').each(function() {
                var rect = this.getBoundingClientRect();
                if (clientY < rect.top + rect.height / 2) { idx = +$(this).data('r'); $before = $(this); return false; }
            });
            if (idx === null) idx = ((afterState[b].courses[c].oqims[o] || {}).rows || []).length;
            if (dropHint && dropHint.b === b && dropHint.c === c && dropHint.o === o && dropHint.idx === idx && $('#mn-body .mn-drop-line').length) return;
            $('#mn-body .mn-drop-line').remove();
            var srcRow = afterState[dragSrc.b].courses[dragSrc.c].oqims[dragSrc.o].rows[dragSrc.r] || {};
            var $line = $('<div class="mn-drop-line"></div>').text('⤵ ' + (srcRow.name || 'guruh') + ' — shu yerga tushadi');
            if ($before) $before.before($line); else $oqim.append($line);
            dropHint = { b: b, c: c, o: o, idx: idx };
        }
        $(document).on('dragover', '#mn-body .mn-oqim, #mn-body .mn-new', function(e) {
            if (!dragSrc) return;
            var lvl = +$(this).closest('.mn-course').data('lvl');
            var srcLvl = ctLevelNum(afterState[dragSrc.b].courses[dragSrc.c]);
            if (lvl !== srcLvl) return; // boshqa kursga tashlash taqiqlanadi
            e.preventDefault();
            if (e.originalEvent.dataTransfer) e.originalEvent.dataTransfer.dropEffect = 'move';
            $('#mn-body .mn-over').not(this).removeClass('mn-over');
            $(this).addClass('mn-over');
            if ($(this).hasClass('mn-oqim')) {
                mnPlaceDropLine($(this), +$(this).data('b'), +$(this).data('c'), +$(this).data('o'), e.originalEvent.clientY);
            } else {
                mnRemoveDropLine();
            }
        });
        $(document).on('dragleave', '#mn-body .mn-oqim, #mn-body .mn-new', function(e) {
            // Ichki elementga o'tishda ham dragleave keladi — faqat haqiqatan chiqib ketganda tozalaymiz
            var rt = e.originalEvent.relatedTarget;
            if (rt && this.contains(rt)) return;
            $(this).removeClass('mn-over');
            if ($(this).hasClass('mn-oqim')) mnRemoveDropLine();
        });
        $(document).on('drop', '#mn-body .mn-oqim', function(e) {
            $(this).removeClass('mn-over');
            if (!dragSrc) return;
            e.preventDefault();
            e.stopPropagation();
            var tb = +$(this).data('b'), tc = +$(this).data('c'), to = +$(this).data('o');
            var tr;
            if (dropHint && dropHint.b === tb && dropHint.c === tc && dropHint.o === to) {
                tr = dropHint.idx; // ko'rsatkich turgan joy
            } else {
                var $row = $(e.target).closest('.mn-row');
                if ($row.length) tr = +$row.data('r');
            }
            mnRemoveDropLine();
            var s = dragSrc; dragSrc = null;
            mnMove(s, tb, tc, to, tr);
        });
        $(document).on('drop', '#mn-body .mn-new', function(e) {
            $(this).removeClass('mn-over');
            if (!dragSrc) return;
            e.preventDefault();
            mnRemoveDropLine();
            var tb = +$(this).data('b'), tc = +$(this).data('c');
            var s = dragSrc; dragSrc = null;
            mnMove(s, tb, tc, -1);
        });

        // Sudrab chetga borganda avtomatik skroll: ichki maydon (#mn-body), oyna va kurslar qatori (gorizontal).
        // Brauzer ichki overflow konteynerini drag paytida o'zi aylantirmaydi — shu sabab qo'lda qilamiz.
        var mnAS = { x: 0, y: 0, hx: null, raf: null };
        $(document).on('dragover', function(e) {
            if (!dragSrc) return;
            mnAS.x = e.originalEvent.clientX; mnAS.y = e.originalEvent.clientY;
            var hx = $(e.target).closest('.mn-courses')[0];
            if (hx) mnAS.hx = hx;
            if (!mnAS.raf) mnAS.raf = requestAnimationFrame(mnAutoScrollTick);
        });
        function mnAutoScrollTick() {
            mnAS.raf = null;
            if (!dragSrc) return;
            var EDGE = 80, MAX = 24;
            function speed(d) { return Math.ceil(Math.min(1, Math.max(0, d) / EDGE) * MAX); }
            var body = document.getElementById('mn-body');
            if (body) {
                var r = body.getBoundingClientRect();
                var top = Math.max(r.top, 0), bottom = Math.min(r.bottom, window.innerHeight);
                if (mnAS.y < top + EDGE) body.scrollTop -= speed(top + EDGE - mnAS.y);
                else if (mnAS.y > bottom - EDGE) body.scrollTop += speed(mnAS.y - (bottom - EDGE));
            }
            // Oyna (sahifa) — maydonning pastki qismi ekrandan tashqarida bo'lsa
            if (mnAS.y < EDGE) window.scrollBy(0, -speed(EDGE - mnAS.y));
            else if (mnAS.y > window.innerHeight - EDGE) window.scrollBy(0, speed(mnAS.y - (window.innerHeight - EDGE)));
            // Kurslar qatori — gorizontal
            if (mnAS.hx) {
                var hr = mnAS.hx.getBoundingClientRect();
                if (mnAS.x < hr.left + EDGE) mnAS.hx.scrollLeft -= speed(hr.left + EDGE - mnAS.x);
                else if (mnAS.x > hr.right - EDGE) mnAS.hx.scrollLeft += speed(mnAS.x - (hr.right - EDGE));
            }
            mnAS.raf = requestAnimationFrame(mnAutoScrollTick);
        }

        // "⇢" — guruhni ro'yxatdan tanlab boshqa oqim/fakultetga ko'chirish (uzoq masofa uchun)
        $(document).on('click', '#mn-body .mn-mv', function(e) {
            e.stopPropagation();
            $('.mn-mv-sel').remove();
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o'), r = +$(this).data('r');
            var lvl = ctLevelNum(afterState[b].courses[c]);
            var $sel = $('<select class="mn-mv-sel"></select>');
            $sel.append('<option value="">Qayerga ko\'chirilsin?</option>');
            afterState.forEach(function(bl, bi) {
                (bl.courses || []).forEach(function(co, ci) {
                    if (ctLevelNum(co) !== lvl) return;
                    var $grp = $('<optgroup></optgroup>').attr('label', bl.title + ' · ' + (co.level_name || ''));
                    (co.oqims || []).forEach(function(oq, oi) {
                        if (bi === b && ci === c && oi === o) return;
                        $grp.append($('<option>').val(bi + '|' + ci + '|' + oi).text((oq.label || 'Oqim') + ' · ' + (oq.total || 0) + ' ta · ' + (MN_LANG_LBL[oq.lang || 'uz'] || oq.lang)));
                    });
                    $grp.append($('<option>').val(bi + '|' + ci + '|-1').text('＋ Yangi oqim'));
                    $sel.append($grp);
                });
            });
            // Oqim kartochkasi overflow:hidden — ro'yxat kesilmasligi uchun body ga, fixed joylashuvda chiqaramiz
            var rr = $(this).closest('.mn-row')[0].getBoundingClientRect();
            var top = rr.bottom + 2, left = rr.left, width = Math.max(rr.width, 260);
            if (left + width > window.innerWidth - 8) left = Math.max(8, window.innerWidth - 8 - width);
            if (top + 40 > window.innerHeight) top = Math.max(8, rr.top - 40);
            $sel.css({ position: 'fixed', top: top + 'px', left: left + 'px', width: width + 'px' }).appendTo('body');
            $sel.attr('size', Math.min(12, Math.max(4, $sel.find('option').length))); // ochiq ro'yxat — focus() ga bog'liq emas
            $sel.focus();
            var closeSel = function() { $sel.remove(); $(window).off('scroll.mnmv resize.mnmv'); $('#mn-body').off('scroll.mnmv'); };
            $sel.on('change click', function() {
                var v = this.value;
                if (!v) return;
                closeSel();
                var pp = v.split('|');
                mnMove({ b: b, c: c, o: o, r: r }, +pp[0], +pp[1], +pp[2]);
            }).on('blur', function() { setTimeout(closeSel, 150); })
              .on('keydown', function(e) { if (e.key === 'Escape') closeSel(); });
            $(window).on('scroll.mnmv resize.mnmv', closeSel);
            $('#mn-body').on('scroll.mnmv', closeSel);
        });

        // "Yangi oqim" ni BOSISH — bo'sh oqim ochiladi (guruhlar keyin sudrab joylanadi)
        $(document).on('click', '#mn-body .mn-new', function() {
            if (!CAN_APPROVE) return;
            var b = +$(this).data('b'), c = +$(this).data('c');
            var course = afterState[b].courses[c];
            // Kursdagi ustun til — yangi oqimning boshlang'ich tili
            var cnt = {};
            (course.oqims || []).forEach(function(o) { (o.rows || []).forEach(function(r) { var lg = r.lang || o.lang || 'uz'; cnt[lg] = (cnt[lg] || 0) + 1; }); });
            var lang = Object.keys(cnt).sort(function(x, y) { return cnt[y] - cnt[x]; })[0] || 'uz';
            mnPushUndo();
            course.oqims.push({ label: 'Oqim-' + (course.oqims.length + 1), lang: lang, total: 0, rows: [] });
            renderManual();
            mnFlash("Bo'sh oqim ochildi — guruhlarni unga sudrab joylang");
        });

        // Bazada nofaol yoki yo'q guruhlarni (server tekshiruvi + tasdiq bilan) ro'yxatdan o'chirish
        function removeInactiveGroups() {
            if (!afterState || !afterState.length) { mnFlash('Ekranda guruh yo\'q.'); return; }
            var snap = JSON.stringify(afterState);
            $('#mn-hemis-status').css('color', '#0369a1').text('Nofaol/yo\'q guruhlar bazada tekshirilmoqda...');
            mnRemoveMissingGroups(function(removedNames) {
                if (!removedNames.length) { $('#mn-hemis-status').css('color', '#64748b').text('Nofaol yoki bazada yo\'q guruh topilmadi (yoki o\'chirish bekor qilindi). Guruh HEMISda nofaol bo\'lsa-yu bu yerda faol ko\'rinsa — avval "Guruhlarni HEMISdan tortish" ni bajaring.'); return; }
                MN_UNDO.push(snap); if (MN_UNDO.length > 30) MN_UNDO.shift(); $('#mn-undo').prop('disabled', false);
                mnRecalc(); renderManual(); renderAfterBody();
                $('#mn-hemis-status').css('color', '#16a34a').text('✓ ' + removedNames.length + ' ta nofaol/yo\'q guruh olib tashlandi: ' + removedNames.slice(0, 12).join(', ') + (removedNames.length > 12 ? ' ...' : ''));
                mnFlash(removedNames.length + ' ta guruh o\'chirildi (↶ Bekor qilish bilan qaytariladi)');
            });
        }

        // Talabasi yo'q (0) barcha guruhlarni ro'yxatdan o'chirish
        function removeEmptyGroups() {
            var n = 0;
            afterState.forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                (oq.rows || []).forEach(function(r) { if (!(+r.count > 0)) n++; });
            }); }); });
            if (!n) { mnFlash("Talabasi yo'q guruh topilmadi."); return; }
            if (!confirm(n + " ta bo'sh (0 talaba) guruh ro'yxatdan o'chirilsinmi? Keyin \"↶ Bekor qilish\" bilan qaytarish mumkin.")) return;
            mnPushUndo();
            afterState.forEach(function(bl) { (bl.courses || []).forEach(function(co) {
                (co.oqims || []).forEach(function(oq) { oq.rows = (oq.rows || []).filter(function(r) { return +r.count > 0; }); });
                co.oqims = (co.oqims || []).filter(function(oq) { return oq.rows.length > 0; });
            }); });
            mnRecalc(); renderManual(); renderAfterBody();
            mnFlash(n + " ta bo'sh guruh o'chirildi");
        }

        // Guruh nomini bosish — bazadagi haqiqiy holat (faol talabalar, statuslar, oxirgi import)
        var GROUP_CHECK_URL = '{{ route("admin.reports.oqim.group.check") }}';
        $(document).on('click', '#mn-body .mn-name-chk', function(e) {
            e.stopPropagation();
            var gid = +$(this).data('gid'), onScreen = +$(this).data('cnt') || 0, nm = $(this).attr('title').split(' — ')[0];
            var $ov = $('#gc-overlay');
            if (!$ov.length) {
                $ov = $('<div id="gc-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998;"><div style="position:absolute;top:6%;left:50%;transform:translateX(-50%);width:92%;max-width:760px;max-height:86vh;background:#fff;border-radius:12px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);">' +
                    '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid #e2e8f0;"><div id="gc-title" style="font-weight:800;color:#1e293b;"></div><button type="button" onclick="$(\'#gc-overlay\').hide()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;">×</button></div>' +
                    '<div id="gc-body" style="padding:12px 18px;overflow:auto;flex:1;font-size:13px;"></div></div></div>').appendTo('body');
            }
            $('#gc-title').text('🔍 ' + nm + ' — bazadagi holat');
            $('#gc-body').html('<div style="color:#94a3b8;">Yuklanmoqda...</div>');
            $ov.show();
            $.get(GROUP_CHECK_URL, { gid: gid }).done(function(r) {
                var h = '';
                var db = +r.active_count || 0;
                var col = db === onScreen ? '#166534' : '#b45309';
                h += '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;">' +
                     '<div style="padding:8px 12px;border-radius:8px;background:#f1f5f9;"><div style="font-size:11px;color:#64748b;font-weight:700;">EKRANDA</div><div style="font-size:20px;font-weight:800;">' + onScreen + '</div></div>' +
                     '<div style="padding:8px 12px;border-radius:8px;background:#f0fdf4;"><div style="font-size:11px;color:#64748b;font-weight:700;">BAZADA (faol, status 11)</div><div style="font-size:20px;font-weight:800;color:' + col + '">' + db + '</div></div>' +
                     '<div style="padding:8px 12px;border-radius:8px;background:#fefce8;flex:1;min-width:220px;font-size:12px;color:#713f12;">' +
                     'Oxirgi talabalar importi: <b>' + (r.last_import && r.last_import.finished_at ? r.last_import.finished_at + ' (' + r.last_import.state + (r.last_import.imported != null ? ', ' + r.last_import.imported + ' ta' : '') + ')' : '—') + '</b><br>' +
                     'Bazada talaba yozuvlari oxirgi yangilangan: <b>' + (r.students_max_updated || '—') + '</b></div></div>';
                if (r.group) h += '<div style="margin-bottom:8px;color:#475569;">Guruh: <b>' + esc(r.group.name) + '</b> #' + gid + ' · ' + esc(r.group.department || '') + ' · ' + esc(r.group.lang || '') + ' · reja ' + esc(r.group.curriculum) + ' · ' + (r.group.active ? 'faol' : '<span style="color:#dc2626">nofaol</span>') + (r.excluded ? ' · <span style="color:#b91c1c;font-weight:700;">hisobdan chiqarilgan</span>' + (r.override_note ? ' (' + esc(r.override_note) + ')' : '') : '') + '</div>';
                if (!r.group) h += '<div style="margin-bottom:8px;color:#dc2626;">Guruh bazada (groups jadvalida) topilmadi — "Guruhlarni HEMISdan tortish" ni bosing.</div>';
                if (CAN_APPROVE && r.group) {
                    h += '<div style="margin-bottom:10px;">' + (r.excluded
                        ? '<button type="button" class="af-btn af-draft" data-lang="' + esc(r.override_lang || '') + '" onclick="excludeGroup(' + gid + ', false, this)">↩ Hisobga qaytarish</button>'
                        : '<button type="button" class="af-btn af-unapprove" data-lang="' + esc(r.override_lang || '') + '" onclick="excludeGroup(' + gid + ', true, this)" title="HEMISda o\'chirilgan/nofaol, lekin API hali qaytarayotgan sharpa guruh — hisobdan chiqariladi, ekrandan olib tashlanadi, keyingi tortishlarda qaytmaydi">🚫 Sharpa/nofaol — hisobdan chiqarish</button>')
                        + ' <span style="font-size:11px;color:#64748b;">HEMISda bunday guruh yo\'q yoki nofaol bo\'lsa-yu bu yerda faol ko\'rinsa</span></div>';
                }
                if (db !== onScreen) h += '<div style="margin-bottom:8px;padding:8px 10px;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">Ekrandagi son bazadagidan farq qiladi — <b>"⟳ Bazadan yangilash"</b> ni bosing (yoki qatordagi sonni qo\'lda tuzating).</div>';
                h += '<div style="margin-bottom:6px;font-weight:800;color:#334155;">Statuslar kesimi (bazada):</div><table style="border-collapse:collapse;margin-bottom:12px;">';
                (r.by_status || []).forEach(function(b) { h += '<tr><td style="padding:2px 10px 2px 0;">' + esc(b.student_status_name || b.student_status_code) + ' <span style="color:#94a3b8;">(' + esc(b.student_status_code) + ')</span></td><td style="padding:2px 0;font-weight:700;text-align:right;">' + b.c + '</td></tr>'; });
                h += '</table>';
                h += '<div style="margin-bottom:6px;font-weight:800;color:#334155;">Faol talabalar (' + db + ' ta' + ((r.active || []).length < db ? ', birinchi ' + (r.active || []).length + ' tasi' : '') + '):</div>';
                h += '<table style="width:100%;border-collapse:collapse;font-size:12.5px;"><thead><tr style="color:#64748b;text-align:left;border-bottom:1px solid #e2e8f0;"><th style="padding:3px 6px;">#</th><th style="padding:3px 6px;">F.I.Sh.</th><th style="padding:3px 6px;">HEMIS ID</th><th style="padding:3px 6px;">Kurs</th><th style="padding:3px 6px;">Yangilangan</th></tr></thead><tbody>';
                (r.active || []).forEach(function(a, i) { h += '<tr style="border-bottom:1px solid #f8fafc;"><td style="padding:3px 6px;color:#94a3b8;">' + (i + 1) + '</td><td style="padding:3px 6px;">' + esc(a.name) + '</td><td style="padding:3px 6px;color:#64748b;">' + esc(a.hemis_id) + '</td><td style="padding:3px 6px;">' + esc(a.level || '') + '</td><td style="padding:3px 6px;color:#64748b;">' + esc(a.updated_at || '') + '</td></tr>'; });
                h += '</tbody></table>';
                h += '<div style="margin-top:10px;font-size:11.5px;color:#64748b;line-height:1.5;">HEMISdagi "Talabalar" ustuni bilan solishtiring. Bazadagi faol son HEMISdagidan <b>ko\'p</b> bo\'lsa — ro\'yxatdagi ortiqcha talaba HEMISda boshqa guruhga o\'tgan yoki statusi o\'zgargan, lekin import hali buni olib kelmagan: "⇩ Talabalarni HEMISdan tortish" ni ishga tushiring va tugashini kuting. Talabaning "Yangilangan" vaqti oxirgi importdan eski bo\'lsa — import bu talabani HEMISdan olmagan (API ro\'yxatida yo\'q).</div>';
                $('#gc-body').html(h);
            }).fail(function(xhr) { $('#gc-body').html('<div style="color:#dc2626;">Xatolik (HTTP ' + xhr.status + ').</div>'); });
        });

        // ===== 🩺 Tashxis: EKRAN vs BAZA vs HEMIS =====
        var SCREEN_DIFF_URL = '{{ route("admin.reports.oqim.screen.diff") }}';
        var GROUP_DIAG_URL = '{{ route("admin.reports.oqim.group.diagnose") }}';
        var GROUP_RESYNC_URL = '{{ route("admin.reports.oqim.group.resync") }}';
        function diagOverlay() {
            var $ov = $('#dg-overlay');
            if (!$ov.length) {
                $ov = $('<div id="dg-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998;"><div style="position:absolute;top:4%;left:50%;transform:translateX(-50%);width:94%;max-width:1000px;max-height:90vh;background:#fff;border-radius:12px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);">' +
                    '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid #e2e8f0;"><div style="font-weight:800;color:#0f766e;">🩺 Tashxis — EKRAN vs BAZA vs HEMIS</div><button type="button" onclick="$(\'#dg-overlay\').hide()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;">×</button></div>' +
                    '<div id="dg-body" style="padding:12px 18px;overflow:auto;flex:1;font-size:13px;"></div></div></div>').appendTo('body');
            }
            return $ov;
        }
        function openDiagnose() {
            var $ov = diagOverlay();
            $('#dg-body').html('<div style="color:#94a3b8;">Ekrandagi guruhlar baza bilan solishtirilmoqda...</div>');
            $ov.show();
            var rows = [], noId = 0, merged = 0;
            (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                (oq.rows || []).forEach(function(r) {
                    if (+r.gid > 0) rows.push({ gid: +r.gid, count: +r.count || 0, name: r.name });
                    else if (r.gids && r.gids.length) merged++;
                    else noId++;
                });
            }); }); });
            if (!rows.length) { $('#dg-body').html('<div style="color:#b45309;">Ekranda HEMIS ID\'li guruh yo\'q. Avval "⟳ Bazadan yangilash" bosing (ID biriktiriladi) yoki "Hisoblash" → "Joriy holatdan".</div>'); return; }
            $.ajax({ url: SCREEN_DIFF_URL, method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, contentType: 'application/json', data: JSON.stringify({ rows: rows }) })
            .done(function(r) {
                var h = '';
                h += '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;font-size:12px;color:#334155;">' +
                     '<span style="padding:5px 10px;border-radius:8px;background:#f1f5f9;">Tekshirildi: <b>' + r.checked + '</b> guruh</span>' +
                     '<span style="padding:5px 10px;border-radius:8px;background:#f0fdf4;">Mos: <b>' + r.same + '</b></span>' +
                     '<span style="padding:5px 10px;border-radius:8px;background:' + (r.diff.length ? '#fff7ed' : '#f0fdf4') + ';">Farq bor: <b>' + r.diff.length + '</b></span>' +
                     (noId ? '<span style="padding:5px 10px;border-radius:8px;background:#fefce8;">ID\'siz qator: <b>' + noId + '</b> (tekshirilmadi — "Bazadan yangilash" ID biriktiradi)</span>' : '') +
                     (merged ? '<span style="padding:5px 10px;border-radius:8px;background:#fefce8;">Birlashtirilgan (optimizatsiya) qator: <b>' + merged + '</b> (tekshirilmadi)</span>' : '') +
                     '</div>';
                h += '<div style="margin-bottom:10px;padding:8px 10px;border-radius:8px;background:#ecfeff;border:1px solid #a5f3fc;font-size:12px;color:#155e75;">' +
                     '<b>🔎 HEMIS API xom javobi:</b> <input id="dg-probe-name" placeholder="guruh nomi (d21-17b)" style="border:1px solid #67e8f9;border-radius:6px;padding:3px 8px;font-size:12px;width:180px;"> ' +
                     '<button type="button" class="af-btn af-load" style="padding:3px 8px;font-size:11.5px;" onclick="hemisProbe()">HEMISdan tekshirish</button> ' +
                     '<span style="color:#64748b;">— API guruh yozuvida "active" maydoni bormi, nom bo\'yicha HEMIS va baza yonma-yon.</span><div id="dg-probe-out" style="margin-top:6px;"></div></div>';
                h += '<div style="margin-bottom:10px;padding:8px 10px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;font-size:11.5px;color:#475569;line-height:1.6;">' +
                     'Oxirgi talabalar importi: <b>' + (r.last_import.finished_at || '—') + '</b> (' + r.last_import.state + (r.last_import.imported != null ? ', ' + r.last_import.imported + ' ta' : '') + (r.last_import.error ? ', xato: ' + esc(r.last_import.error) : '') + ') · ' +
                     'Bazadagi talaba yozuvlari oxirgi yangilangan: <b>' + (r.students_max_updated || '—') + '</b> · ' +
                     'HEMIS API: <code>' + esc(r.hemis_base) + '</code> · token: ' + (r.hemis_token_set ? '✓' : '<span style="color:#dc2626">yo\'q</span>') + ' · queue: <code>' + esc(r.queue) + '</code></div>';
                if (!r.diff.length) {
                    h += '<div style="padding:16px;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-weight:700;">✓ Ekrandagi barcha ID\'li guruhlarning soni bazadagi faol (status 11) talaba soni bilan bir xil. Agar HEMISda boshqa son ko\'rsangiz — demak BAZA HEMISdan orqada: guruh qatoridagi "🔬 HEMIS bilan" tugmasi bilan jonli tekshiring.</div>';
                    // baribir jonli tekshirish uchun ro'yxat beramiz (birinchi 40 ta)
                    h += '<div style="margin-top:12px;font-weight:800;color:#334155;">Jonli tekshirish uchun guruhlar:</div>' + diagTable(rows.slice(0, 40).map(function(x) { return { gid: x.gid, name: x.name, screen: x.count, db: x.count, group_in_db: true, group_active: true }; }));
                } else {
                    h += '<div style="margin-bottom:6px;font-weight:800;color:#9a3412;">Ekran ≠ baza bo\'lgan guruhlar:</div>' + diagTable(r.diff);
                    h += '<div style="margin-top:10px;font-size:12px;color:#475569;">Tezkor yechim: <button type="button" class="af-btn af-load" onclick="$(\'#dg-overlay\').hide(); mergeNewGroups();">⟳ Bazadan yangilash</button> — ekrandagi sonlarni bazadagi songa keltiradi. Baza HEMISdan farq qilsa — har qator uchun "🔬 HEMIS bilan" → "⇩ Qayta tortish".</div>';
                }
                $('#dg-body').html(h);
            }).fail(function(xhr) { $('#dg-body').html('<div style="color:#dc2626;">Xatolik (HTTP ' + xhr.status + ').</div>'); });
        }
        var HEMIS_PROBE_URL = '{{ route("admin.reports.oqim.hemis.probe") }}';
        function hemisProbe() {
            var nm = $('#dg-probe-name').val() || '';
            $('#dg-probe-out').html('<span style="color:#94a3b8;">HEMISdan o\'qilmoqda...</span>');
            $.get(HEMIS_PROBE_URL, { name: nm }).done(function(r) {
                var h = '';
                if (!r.ok) { $('#dg-probe-out').html('<span style="color:#dc2626;">HEMIS xato: ' + esc(r.error || '') + ' (' + esc(r.url) + ')</span>'); return; }
                var hasActive = !!r.active_field_detected;
                h += '<div>API yozuv maydonlari (' + (r.total != null ? 'jami ' + r.total + ' guruh' : '') + '): <code style="font-size:11px;">' + esc((r.sample_keys || []).join(', ')) + '</code></div>';
                h += '<div style="font-weight:800;color:' + (hasActive ? '#166534' : '#b91c1c') + ';">' + (hasActive ? '✓ "active" maydoni BOR — nofaollik API dan olinadi.' : '✗ "active" maydoni YO\'Q — API nofaollikni bildirmaydi; import hammasini faol deb yozadi.') + '</div>';
                if (nm) {
                    h += '<div style="margin-top:6px;"><b>HEMIS API da "' + esc(nm) + '":</b> ' + ((r.matches || []).length ? '' : '<i>topilmadi (API search parametrini qo\'llamasa, faqat 1-sahifa tekshiriladi' + (r.search_page_count ? ', sahifalar: ' + r.search_page_count : '') + ')</i>');
                    (r.matches || []).forEach(function(m) { h += '<div>• #' + m.id + ' ' + esc(m.name) + ' — active: <b>' + (m.active === null ? 'MAYDON YO\'Q' : (m.active ? 'faol' : 'NOFAOL')) + '</b> <code style="font-size:10px;">' + esc(JSON.stringify(m.active_raw || {})) + '</code> · ' + esc(m.department || '') + ' · ' + esc(m.lang || '') + ' · reja ' + esc(m.curriculum) + (Object.keys(m.other_flags || {}).length ? ' · ' + esc(JSON.stringify(m.other_flags)) : '') + '</div>'; });
                    h += '</div><div style="margin-top:4px;"><b>Bazada:</b>';
                    (r.db || []).forEach(function(d) { h += '<div>• #' + d.group_hemis_id + ' ' + esc(d.name) + ' — active: <b>' + (d.active ? 'faol' : 'nofaol') + '</b> · ' + esc(d.department_name || '') + ' · ' + esc(d.education_lang_name || '') + ' · reja ' + esc(d.curriculum_hemis_id) + ' · ' + esc(d.updated_at) + '</div>'; });
                    h += '</div>';
                }
                if (r.sample) h += '<details style="margin-top:6px;"><summary style="cursor:pointer;color:#0e7490;">Birinchi yozuv namunasi</summary><pre style="font-size:10.5px;white-space:pre-wrap;">' + esc(JSON.stringify(r.sample, null, 1)) + '</pre></details>';
                $('#dg-probe-out').html(h);
            }).fail(function(xhr) { $('#dg-probe-out').html('<span style="color:#dc2626;">Xatolik (HTTP ' + xhr.status + ')</span>'); });
        }
        function diagTable(list) {
            var h = '<table style="width:100%;border-collapse:collapse;font-size:12.5px;"><thead><tr style="color:#64748b;text-align:left;border-bottom:2px solid #e2e8f0;">' +
                    '<th style="padding:5px 6px;">Guruh</th><th style="padding:5px 6px;text-align:right;">Ekran</th><th style="padding:5px 6px;text-align:right;">Baza</th><th style="padding:5px 6px;text-align:right;">HEMIS</th><th style="padding:5px 6px;">Holat</th><th style="padding:5px 6px;"></th></tr></thead><tbody>';
            list.forEach(function(d) {
                var flag = !d.group_in_db ? '<span style="color:#dc2626;font-weight:700;">guruh bazada yo\'q</span>' : (d.excluded ? '<span style="color:#b91c1c;font-weight:700;">hisobdan chiqarilgan</span>' : (d.group_active === false ? '<span style="color:#b45309;font-weight:700;">guruh nofaol</span>' : (d.screen !== d.db ? '<span style="color:#9a3412;">ekran eskirgan</span>' : '')));
                h += '<tr id="dg-r-' + d.gid + '" style="border-bottom:1px solid #f1f5f9;">' +
                     '<td style="padding:5px 6px;font-weight:700;">' + esc(d.name) + ' <span style="color:#94a3b8;font-weight:400;">#' + d.gid + '</span></td>' +
                     '<td style="padding:5px 6px;text-align:right;">' + d.screen + '</td>' +
                     '<td style="padding:5px 6px;text-align:right;font-weight:700;color:' + (d.screen === d.db ? '#166534' : '#9a3412') + ';" class="dg-db">' + d.db + '</td>' +
                     '<td style="padding:5px 6px;text-align:right;" class="dg-hemis">—</td>' +
                     '<td style="padding:5px 6px;" class="dg-flag">' + flag + '</td>' +
                     '<td style="padding:5px 6px;white-space:nowrap;"><button type="button" class="af-btn af-load" style="padding:3px 8px;font-size:11.5px;" onclick="diagGroup(' + d.gid + ', ' + d.screen + ')">🔬 HEMIS bilan</button> ' +
                     (CAN_APPROVE ? '<button type="button" class="af-btn af-draft" style="padding:3px 8px;font-size:11.5px;" onclick="resyncGroup(' + d.gid + ', ' + d.screen + ')">⇩ Qayta tortish</button>' : '') + '</td></tr>' +
                     '<tr id="dg-d-' + d.gid + '" style="display:none;"><td colspan="6" style="padding:6px 10px 10px;background:#f8fafc;"></td></tr>';
            });
            return h + '</tbody></table>';
        }
        function diagGroup(gid, screen) {
            var $d = $('#dg-d-' + gid).show(); $d.find('td').html('<span style="color:#94a3b8;">HEMISdan jonli o\'qilmoqda...</span>');
            $.get(GROUP_DIAG_URL, { gid: gid, screen: screen }).done(function(r) {
                $('#dg-r-' + gid + ' .dg-hemis').text(r.hemis_ok ? r.hemis : 'xato').css('color', r.hemis_ok && r.hemis === r.db ? '#166534' : '#dc2626');
                $('#dg-r-' + gid + ' .dg-db').text(r.db);
                var col = r.action === 'ok' ? '#166534' : (r.action === 'config' ? '#dc2626' : '#9a3412');
                var h = '<div style="font-weight:800;color:' + col + ';margin-bottom:6px;">' + (r.verdict || []).map(esc).join('<br>') + '</div>';
                h += '<div style="font-size:11.5px;color:#64748b;margin-bottom:6px;">HEMIS guruhda jami: ' + (r.hemis_total_in_group != null ? r.hemis_total_in_group : '—') + ' (faol: ' + (r.hemis != null ? r.hemis : '—') + ') · Baza statuslar: ' + Object.keys(r.db_by_status || {}).map(function(k) { return esc(k) + ' ' + r.db_by_status[k]; }).join(', ') + ' · <code style="font-size:10.5px;">' + esc(r.hemis_url) + '</code></div>';
                if ((r.extra_in_db || []).length) {
                    h += '<div style="font-weight:700;color:#9a3412;margin-top:4px;">Bazada faol, lekin HEMISda bu guruhda faol emas (' + r.extra_in_db.length + '):</div><ul style="margin:2px 0 6px 18px;">';
                    r.extra_in_db.forEach(function(x) { h += '<li>' + esc(x.name) + ' <span style="color:#94a3b8;">#' + x.hemis_id + '</span> — ' + esc(x.reason) + (x.db_updated ? ' · bazada yangilangan: ' + x.db_updated : '') + '</li>'; });
                    h += '</ul>';
                }
                if ((r.missing_in_db || []).length) {
                    h += '<div style="font-weight:700;color:#1d4ed8;margin-top:4px;">HEMISda faol, lekin bazada bu guruhda faol emas (' + r.missing_in_db.length + '):</div><ul style="margin:2px 0 6px 18px;">';
                    r.missing_in_db.forEach(function(x) { h += '<li>' + esc(x.name) + ' <span style="color:#94a3b8;">#' + x.hemis_id + '</span> — bazada: ' + esc(x.db_state) + '</li>'; });
                    h += '</ul>';
                }
                if (r.action === 'resync' && CAN_APPROVE) h += '<button type="button" class="af-btn af-approve" style="padding:4px 10px;font-size:12px;" onclick="resyncGroup(' + gid + ', ' + (screen == null ? 'null' : screen) + ')">⇩ Shu guruhni HEMISdan qayta tortish</button>';
                if (r.action === 'refresh') h += '<button type="button" class="af-btn af-load" style="padding:4px 10px;font-size:12px;" onclick="$(\'#dg-overlay\').hide(); mergeNewGroups();">⟳ Bazadan yangilash</button>';
                $d.find('td').html(h);
            }).fail(function(xhr) { $d.find('td').html('<span style="color:#dc2626;">Xatolik (HTTP ' + xhr.status + ').</span>'); });
        }
        function resyncGroup(gid, screen) {
            var $d = $('#dg-d-' + gid).show(); $d.find('td').html('<span style="color:#0369a1;">Guruh HEMISdan qayta tortilmoqda...</span>');
            $.ajax({ url: GROUP_RESYNC_URL, method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, data: { gid: gid } }).done(function(r) {
                $('#dg-r-' + gid + ' .dg-db').text(r.db);
                // Ekrandagi qatorni ham darhol yangilaymiz
                var changed = 0;
                (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                    (oq.rows || []).forEach(function(rw) { if (+rw.gid === gid && (+rw.count || 0) !== r.db) { rw.count = r.db; changed++; } });
                }); }); });
                if (changed) { mnRecalc(); renderManual(); renderAfterBody(); }
                $d.find('td').html('<span style="color:#166534;font-weight:700;">✓ Qayta tortildi: ' + r.imported + ' ta yuklandi, ' + r.deactivated + ' ta chetlashtirildi. Bazada faol: ' + r.db + (changed ? ' — ekran yangilandi.' : '.') + '</span> <button type="button" class="af-btn af-load" style="padding:3px 8px;font-size:11.5px;" onclick="diagGroup(' + gid + ', ' + r.db + ')">🔬 Qayta tekshirish</button>');
            }).fail(function(xhr) { $d.find('td').html('<span style="color:#dc2626;">Xatolik: ' + ((xhr.responseJSON && xhr.responseJSON.error) || ('HTTP ' + xhr.status)) + '</span>'); });
        }

        // Sharpa/nofaol guruhni hisobdan chiqarish ("Guruh tuzatish" override) va ekrandan olib tashlash
        var OVERRIDE_SAVE_URL = '{{ route("admin.reports.oqim.overrides.save") }}';
        function excludeGroup(gid, exclude, btn) {
            var name = '';
            (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) { (oq.rows || []).forEach(function(r) { if (+r.gid === gid) name = r.hemis_name || r.name; }); }); }); });
            $(btn).prop('disabled', true);
            var keepLang = $(btn).data('lang') || ''; // mavjud til tuzatishi saqlanadi
            var payload = { group_hemis_id: gid, group_name: name, excluded: exclude ? 1 : 0, note: exclude ? 'Oqim: sharpa/nofaol guruh (' + new Date().toLocaleDateString() + ')' : '' };
            if (keepLang) payload.lang = keepLang;
            $.ajax({ url: OVERRIDE_SAVE_URL, method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, data: payload })
            .done(function() {
                if (exclude) {
                    mnPushUndo();
                    var n = 0;
                    (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) {
                        (co.oqims || []).forEach(function(oq) { oq.rows = (oq.rows || []).filter(function(r) { if (+r.gid === gid) { n++; return false; } return true; }); });
                        co.oqims = (co.oqims || []).filter(function(oq) { return (oq.rows || []).length > 0; });
                    }); });
                    mnRecalc(); renderManual(); renderAfterBody();
                    $('#gc-overlay').hide();
                    mnFlash('#' + gid + ' ' + name + ' hisobdan chiqarildi' + (n ? ', ekrandan olib tashlandi' : '') + ' (↶ Bekor qilish — faqat ekranni qaytaradi)');
                } else {
                    $(btn).prop('disabled', false).text('✓ Hisobga qaytarildi — "Bazadan yangilash" bilan ro\'yxatga qo\'shing');
                }
            }).fail(function(xhr) { $(btn).prop('disabled', false); mnFlash('Xatolik (HTTP ' + xhr.status + ')'); });
        }

        // Guruhni ro'yxatdan o'chirish (bashoratdagi soxta "1K-01a" kabi guruhlar uchun)
        $(document).on('click', '#mn-body .mn-x-row', function(e) {
            e.stopPropagation();
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o'), r = +$(this).data('r');
            var oq = afterState[b].courses[c].oqims[o];
            var row = oq.rows[r];
            if ((+row.count || 0) > 0 && !confirm('"' + row.name + '" guruhida ' + row.count + ' ta talaba bor. Baribir ro\'yxatdan o\'chirilsinmi?')) return;
            mnPushUndo();
            oq.rows.splice(r, 1);
            if (!oq.rows.length) afterState[b].courses[c].oqims.splice(o, 1);
            mnRecalc(); renderManual(); renderAfterBody();
            mnFlash('"' + row.name + '" o\'chirildi (↶ Bekor qilish bilan qaytarish mumkin)');
        });
        // Oqimni barcha guruhlari bilan o'chirish
        $(document).on('click', '#mn-body .mn-x-oqim', function(e) {
            e.stopPropagation();
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o');
            var oq = afterState[b].courses[c].oqims[o];
            if (!confirm('"' + (oq.label || 'Oqim') + '" oqimi ' + (oq.rows || []).length + ' ta guruhi (' + (oq.total || 0) + ' talaba) bilan ro\'yxatdan o\'chirilsinmi?')) return;
            mnPushUndo();
            afterState[b].courses[c].oqims.splice(o, 1);
            mnRecalc(); renderManual(); renderAfterBody();
            mnFlash('Oqim o\'chirildi (↶ Bekor qilish bilan qaytarish mumkin)');
        });

        // Talaba sonini tahrirlash — jami qiymatlar joyida yangilanadi
        $(document).on('input', '#mn-body .mn-cnt', function() {
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o'), r = +$(this).data('r');
            var v = parseInt(this.value, 10); if (isNaN(v) || v < 0) v = 0;
            afterState[b].courses[c].oqims[o].rows[r].count = v;
            mnRecalc();
            $('#mn-body [data-mnot="' + b + '-' + c + '-' + o + '"]').text(afterState[b].courses[c].oqims[o].total + ' ta');
            $('#mn-body [data-mnct="' + b + '-' + c + '"]').text(afterState[b].courses[c].total);
            var grand = 0;
            for (var i = 0; i < afterState.length; i++)
                for (var j = 0; j < afterState[i].courses.length; j++) grand += (+afterState[i].courses[j].total || 0);
            $('#mn-total-badge').text(rejaPrefix() + 'Jami talaba: ' + grand + ' ta · qo\'lda tuzatish');
        });
        // Oqim nomini tahrirlash
        $(document).on('input', '#mn-body .mn-label', function() {
            var b = +$(this).data('b'), c = +$(this).data('c'), o = +$(this).data('o');
            afterState[b].courses[c].oqims[o].label = this.value;
        });

        // Guruh nomini solishtirish uchun normallashtirish: til qavsi olib tashlanadi,
        // kirill o'xshash harflar lotinga keltiriladi, bo'shliqlar/registr tekislanadi.
        function mnNormName(n) {
            return String(n || '')
                .replace(/\s*\((?:o['’‘]?z|oz|uz|rus|ru|ing|eng|ang)\s*\)\s*$/i, '')
                .replace(/\s*\(\s*([a-zA-Zа-яА-Я])\s*\)/g, '$1') // HEMIS: "d1/d25-01(a)" == "d1/d25-01a"
                .replace(/[аА]/g, 'a').replace(/[еЕ]/g, 'e').replace(/[сС]/g, 'c').replace(/[оО]/g, 'o').replace(/[рР]/g, 'p').replace(/[хХ]/g, 'x')
                .replace(/\s+/g, '').toLowerCase();
        }

        // Bazadagi (HEMISdan tortilgan) yangi guruhlarni ekrandagi ro'yxatga qo'shadi.
        // Joriy holat (bo'sh guruhlar bilan) serverdan olinadi; ekranda yo'q guruhlar
        // tegishli fakultet/kursga "Yangi (HEMIS)" oqimi sifatida qo'shiladi.
        // Mavjud joylashuv (drag & drop natijasi) o'zgarmaydi.
        // Blok kaliti: fakultet + yo'nalish SHIFRI + ta'lim turi. Serverdan block_key kelsa — o'sha;
        // eski qoralamalar uchun sarlavhadan: qavsdagi izoh ("(yo'nalishlar bo'yicha)") olib tashlanadi,
        // shunda "Stomatologiya" va "Stomatologiya (yo'nalishlar bo'yicha)" bitta blok bo'ladi.
        function mnBlockKey(bl) {
            if (bl.block_key) return 'k|' + bl.block_key;
            var t = String(bl.title || '');
            var track = /Qo['’‘]?shma/i.test(t) ? 'qoshma' : 'oddiy';
            t = t.replace(/\s*—\s*Qo['’‘]?shma ta['’‘]?lim\s*$/i, '').replace(/\([^)]*\)/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
            return 't|' + t + '|' + track;
        }
        // Bir xil fakultet+yo'nalish uchun ikki blok bo'lsa — ikkinchisining kurslari/oqimlari birinchisiga qo'shiladi
        function mnMergeDuplicateBlocks() {
            var byKey = {}, out = [], merged = 0;
            (afterState || []).forEach(function(bl) {
                var k = mnBlockKey(bl);
                // block_key bo'lgan blok bilan sarlavha-kalitli eski blokni ham moslash uchun ikkinchi kalit
                var k2 = 't|' + String(bl.title || '').replace(/\s*—\s*Qo['’‘]?shma ta['’‘]?lim\s*$/i, '').replace(/\([^)]*\)/g, '').replace(/\s+/g, ' ').trim().toLowerCase() + '|' + (/Qo['’‘]?shma/i.test(bl.title || '') ? 'qoshma' : 'oddiy');
                var tgt = byKey[k] || byKey[k2];
                if (!tgt) { byKey[k] = bl; byKey[k2] = bl; out.push(bl); return; }
                merged++;
                (bl.courses || []).forEach(function(co) {
                    var tc = (tgt.courses || []).find(function(c) { return ctLevelNum(c) === ctLevelNum(co); });
                    if (!tc) { tgt.courses.push(co); return; }
                    (co.oqims || []).forEach(function(oq) {
                        // Bir xil nomli oqim bo'lsa — nomiga raqam qo'shamiz
                        if (tc.oqims.some(function(o) { return o.label === oq.label; })) oq.label = (oq.label || 'Oqim') + '-' + (tc.oqims.length + 1);
                        tc.oqims.push(oq);
                    });
                });
                if (!tgt.block_key && bl.block_key) tgt.block_key = bl.block_key;
            });
            if (merged) afterState = out;
            return merged;
        }

        // Bir HEMIS guruhi ikki qator bo'lib qolgan bo'lsa (nom formati o'zgarganda) — birini olib tashlaymiz.
        // "Yangi (HEMIS)" oqimidagi nusxa emas, asl joylashuvdagi qator saqlanadi.
        function mnDedupeByGid() {
            var seen = {}, removed = 0;
            var pass = function(processNew) {
                afterState.forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                    var isNew = /^Yangi/i.test(oq.label || '');
                    if (isNew !== processNew) return; // 1-o'tish (false): asl oqimlar saqlanadi; 2-o'tish (true): "Yangi" oqimdagi nusxalar olib tashlanadi
                    oq.rows = (oq.rows || []).filter(function(r) {
                        if (!(+r.gid > 0)) return true;
                        if (seen[+r.gid]) { removed++; return false; }
                        seen[+r.gid] = true; return true;
                    });
                }); }); });
            };
            pass(false); pass(true);
            afterState.forEach(function(bl) { (bl.courses || []).forEach(function(co) { co.oqims = (co.oqims || []).filter(function(oq) { return (oq.rows || []).length > 0; }); }); });
            return removed;
        }

        // HEMISdagi barcha faol guruhlarni (bazadan, asl nomi bilan) fakultet → kurs → til bo'yicha
        // oqimlarga joylab, ekrandagi holat sifatida yuklaydi. Oldingi joylashuv "Bekor qilish" ga yoziladi.
        function loadFromHemis(pullRes) {
            var $st = $('#mn-hemis-status');
            $st.css('color', '#0369a1').text('Guruhlar fakultet/kurs/til bo\'yicha joylanmoqda...');
            var lf = getFilters(false); lf.projection = 0; delete lf.academic_year; // real holat
            $.get(DATA_URL, lf).done(function(res) {
                var src = res.blocks || [];
                if (!src.length) { $st.css('color', '#b45309').text('Bazada faol guruh topilmadi — filtrlarni (ta\'lim turi, fakultet) tekshiring.'); return; }
                if (afterState && afterState.length) mnPushUndo();
                afterState = JSON.parse(JSON.stringify(src));
                mnMergeDuplicateBlocks();
                manualContext = realContextFromFilters(); // aniq REAL kontekst (reja katagi yoqilgan bo'lsa ham reja sifatida saqlanmaydi)
                manualKnownIds = idSetFromList(res.group_ids || []);
                mnRecalc(); renderManual(); renderAfterBody();
                var groups = 0, students = 0, oqims = 0;
                afterState.forEach(function(bl) { (bl.courses || []).forEach(function(co) { students += (+co.total || 0); (co.oqims || []).forEach(function(oq) { oqims++; groups += (oq.rows || []).length; }); }); });
                var pulled = (MN_PULL_NOTE ? MN_PULL_NOTE + ' ' : (pullRes && pullRes.imported != null ? 'HEMISdan ' + pullRes.imported + ' ta guruh tortildi (yangi ' + (pullRes.created || 0) + '). ' : '')); MN_PULL_NOTE = '';
                $st.css('color', '#16a34a').text('✓ ' + pulled + 'Ekranga ' + groups + ' ta faol guruh (' + students + ' talaba) fakultet → kurs → til bo\'yicha ' + oqims + ' ta oqimga joylandi, nomlar HEMISdagidek. Oldingi joylashuv kerak bo\'lsa — "↶ Bekor qilish".');
                mnFlash(groups + ' ta guruh HEMISdan joylandi');
            }).fail(function(xhr) { $st.css('color', '#dc2626').text('Guruhlarni joylab bo\'lmadi (HTTP ' + xhr.status + ').'); });
        }

        // opts: { countsOnly: faqat sonlar/ID (yangi guruh qo'shilmaydi), silent: xabarsiz, bekor qilish yozuvisiz }
        function mergeNewGroups(opts) {
            opts = opts || {};
            var $st = $('#mn-hemis-status');
            var $btn = $('#mn-merge-new').prop('disabled', true).css('opacity', 0.6);
            if (!opts.silent) $st.css('color', '#0369a1').text('Yangi guruhlar tekshirilmoqda...');
            // Tarixdagi versiya tahrirlanayotgan bo'lsa — o'sha versiyaning konteksti (fakultet,
            // ta'lim turi, reja yili...) bo'yicha so'raymiz; faqat optimize=0 (joriy holat).
            var mf = manualContext ? $.extend(true, {}, manualContext, { optimize: 0 }) : getFilters(false);
            if (manualContext) { delete mf.goal; delete mf.merge_faculties; delete mf._was_plan; }
            mf.projection = 0; delete mf.academic_year; // yangilash DOIM real holatdan — reja rejimi kurslarni suradi
            $.get(DATA_URL, mf).done(function(res) {
                var src = res.blocks || [];
                if (!src.length) { $st.css('color', '#b45309').text('Bazada guruh topilmadi — filtrlarni tekshiring.'); return; }
                if (!afterState || !afterState.length) {
                    // Ekranda hech narsa yo'q — joriy holatni to'liq yuklaymiz
                    afterState = JSON.parse(JSON.stringify(src));
                    manualContext = realContextFromFilters();
                    manualKnownIds = idSetFromList(res.group_ids || []);
                    MN_UNDO = [];
                    mnRecalc(); renderManual(); renderAfterBody();
                    $st.css('color', '#16a34a').text('✓ Joriy holat (barcha guruhlar) yuklandi.');
                    return;
                }
                var snapshot = JSON.stringify(afterState);
                var blocksMerged = mnMergeDuplicateBlocks(); // "Stomatologiya" / "Stomatologiya (…)" kabi takror bloklar
                // Yangi guruh — HEMIS ID bo'yicha aniqlanadi (nom bo'yicha emas: optimizatsiya
                // guruhlarni birlashtirib nomini o'zgartirgan bo'lsa ham ular "yangi" sanalmaydi).
                // ID ma'lumoti umuman bo'lmagan eski versiyalar uchun — nom bo'yicha zaxira usul.
                var knownIds = {}; // ID biriktirish (adoption) dan KEYIN to'ldiriladi — biriktirilgan guruh "yangi" emas
                var useIds = false;
                var have = {};
                afterState.forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                    (oq.rows || []).forEach(function(r) { have[mnNormName(r.name)] = true; });
                }); }); });
                function isNewRow(r) {
                    if (useIds) {
                        if (!(+r.gid > 0)) return false;        // sun'iy (bashorat) qatorlar hech qachon qo'shilmaydi
                        if (knownIds[+r.gid]) return false;
                        knownIds[+r.gid] = true; manualKnownIds[+r.gid] = true;
                        return true;
                    }
                    var k = mnNormName(r.name);
                    if (have[k]) return false;
                    have[k] = true; return true;
                }
                var added = 0, addedNames = [], updated = 0;
                // Mavjud guruhlarning talaba sonini bazadagi (HEMISdan tortilgan) songa yangilaymiz — faqat
                // aniq bitta guruhga (gid) mos qatorlar; birlashtirilgan (gids) qatorlar tegilmaydi.
                // ID'siz qatorlar (ID qo'shilishidan oldin saqlangan qoralama/versiya) nom+til bo'yicha
                // moslanadi va ularga ID biriktiriladi — keyingi yangilashlar ID bo'yicha bo'ladi.
                var cntById = {}, byName = {}, byNameOnly = {}, adopted = 0, unmatched = [];
                src.forEach(function(sb) { (sb.courses || []).forEach(function(sc) { (sc.oqims || []).forEach(function(so) {
                    (so.rows || []).forEach(function(r) {
                        if (+r.gid > 0) {
                            cntById[+r.gid] = +r.count || 0;
                            var nk = mnNormName(r.name);
                            byName[nk + '|' + (r.lang || so.lang || 'uz')] = { gid: +r.gid, count: +r.count || 0 };
                            (byNameOnly[nk] = byNameOnly[nk] || []).push({ gid: +r.gid, count: +r.count || 0 });
                        }
                    });
                }); }); });
                afterState.forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                    (oq.rows || []).forEach(function(r) {
                        if (!(+r.gid > 0) && !(r.gids && r.gids.length)) {
                            // avval nom+til, keyin faqat nom (bitta bo'lsa) — "mehmon" guruh boshqa tildagi oqimda turishi mumkin
                            var nk = mnNormName(r.name);
                            var f = byName[nk + '|' + (r.lang || oq.lang || 'uz')];
                            if (!f && byNameOnly[nk] && byNameOnly[nk].length === 1) f = byNameOnly[nk][0];
                            if (f) { r.gid = f.gid; manualKnownIds[f.gid] = true; adopted++; }
                        }
                        if (+r.gid > 0) {
                            if (cntById.hasOwnProperty(+r.gid)) {
                                if ((+r.count || 0) !== cntById[+r.gid]) { r.count = cntById[+r.gid]; updated++; }
                            } else if (unmatched.length < 8) { unmatched.push(r.name); }
                        } else if (!(r.gids && r.gids.length) && unmatched.length < 8) {
                            unmatched.push(r.name);
                        }
                    });
                }); }); });
                knownIds = $.extend({}, manualKnownIds, idsFromBlocks(afterState));
                useIds = Object.keys(knownIds).length > 0;
                if (!opts.countsOnly) src.forEach(function(sb) {
                    (sb.courses || []).forEach(function(sc) {
                        var lvl = ctLevelNum(sc);
                        var newRows = [];
                        (sc.oqims || []).forEach(function(so) { (so.rows || []).forEach(function(r) {
                            if (isNewRow(r)) newRows.push(JSON.parse(JSON.stringify(r)));
                        }); });
                        if (!newRows.length) return;
                        // Mos blok (fakultet + yo'nalish SHIFRI) va kursni topamiz, bo'lmasa yaratamiz
                        var sk = mnBlockKey(sb);
                        var tb = afterState.find(function(b) { return mnBlockKey(b) === sk; })
                              || afterState.find(function(b) { return mnBlockKey({ title: b.title }) === mnBlockKey({ title: sb.title }); });
                        if (!tb) { tb = { title: sb.title, block_key: sb.block_key, department_name: sb.department_name, courses: [] }; afterState.push(tb); }
                        else if (!tb.block_key && sb.block_key) tb.block_key = sb.block_key;
                        var tc = (tb.courses || []).find(function(c) { return ctLevelNum(c) === lvl; });
                        if (!tc) { tc = { level_code: sc.level_code, level_name: sc.level_name, total: 0, oqims: [] }; tb.courses.push(tc); }
                        // Yangi guruhlar tilga qarab alohida "Yangi (HEMIS)" oqimlariga tushadi
                        var byLang = {};
                        newRows.forEach(function(r) { var lg = r.lang || 'uz'; (byLang[lg] = byLang[lg] || []).push(r); });
                        Object.keys(byLang).forEach(function(lg) {
                            var ex = tc.oqims.find(function(o) { return /^Yangi/i.test(o.label || '') && (o.lang || 'uz') === lg; });
                            if (ex) { ex.rows = ex.rows.concat(byLang[lg]); }
                            else { tc.oqims.push({ label: 'Yangi (HEMIS)', lang: lg, total: 0, rows: byLang[lg] }); }
                        });
                        added += newRows.length;
                        newRows.forEach(function(r) { addedNames.push(r.name); });
                    });
                });
                var dedup = mnDedupeByGid();
                var unm = unmatched.length ? ' Bazada (joriy filtr bo\'yicha) topilmagan guruhlar: ' + unmatched.join(', ') + (unmatched.length >= 8 ? ' ...' : '') + ' — ular bashorat (soxta) guruh yoki filtrdan tashqarida bo\'lishi mumkin.' : '';
                if (opts.silent) {
                    if (adopted || updated || dedup || blocksMerged) { mnRecalc(); renderManual(); renderAfterBody(); }
                    if (updated || dedup || blocksMerged) mnFlash('Bazadan yangilandi' + (updated ? ': ' + updated + ' ta son' : '') + (dedup ? ', ' + dedup + ' ta takroriy qator olib tashlandi' : '') + (blocksMerged ? ', ' + blocksMerged + ' ta takroriy blok birlashtirildi' : ''));
                    return;
                }
                // To'liq rejim: nofaol / HEMISda yo'q guruhlarni ham olib tashlaymiz (server bilan tekshirib)
                if (!opts.countsOnly) {
                    mnRemoveMissingGroups(function(removedNames) {
                        finish(removedNames);
                    });
                    return;
                }
                finish([]);
                function finish(removedNames) {
                var removed = removedNames.length;
                if (!added && !updated && !dedup && !blocksMerged && !removed) {
                    if (adopted) { MN_UNDO.push(snapshot); if (MN_UNDO.length > 30) MN_UNDO.shift(); renderAfterBody(); }
                    $st.css('color', '#64748b').text((MN_PULL_NOTE ? MN_PULL_NOTE + ' ' : '') + 'Sonlar bazadagi bilan bir xil' + (adopted ? ' (' + adopted + ' ta guruhga HEMIS ID biriktirildi — qoralamani saqlang)' : '') + '.' + unm
                        + ' (HEMISda o\'zgarish bo\'lgan bo\'lsa avval "Guruhlarni/Talabalarni HEMISdan tortish" ni bosing.)'); MN_PULL_NOTE = '';
                    return;
                }
                MN_UNDO.push(snapshot); if (MN_UNDO.length > 30) MN_UNDO.shift();
                mnRecalc(); renderManual(); renderAfterBody();
                var msg = '✓ ';
                if (added) msg += added + ' ta yangi guruh qo\'shildi ("Yangi (HEMIS)" oqimlarida: ' + addedNames.slice(0, 6).join(', ') + (addedNames.length > 6 ? ' ...' : '') + ' — kerakli oqimga sudrab joylang). ';
                if (updated) msg += updated + ' ta guruhning talaba soni bazadagi songa yangilandi.';
                if (adopted) msg += ' ' + adopted + ' ta guruhga HEMIS ID biriktirildi.';
                if (dedup) msg += ' ' + dedup + ' ta takroriy qator (bir guruh ikki marta) olib tashlandi.';
                if (blocksMerged) msg += ' ' + blocksMerged + ' ta takroriy blok (bir xil fakultet/yo\'nalish) birlashtirildi.';
                if (removed) msg += ' ' + removed + ' ta nofaol/HEMISda yo\'q guruh olib tashlandi: ' + removedNames.slice(0, 8).join(', ') + (removed > 8 ? ' ...' : '') + '.';
                $st.css('color', '#16a34a').text((MN_PULL_NOTE ? MN_PULL_NOTE + ' ' : '') + msg + unm); MN_PULL_NOTE = '';
                mnFlash((added ? added + ' ta yangi guruh' : '') + (added && updated ? ', ' : '') + (updated ? updated + ' ta son yangilandi' : '') + (dedup ? ', ' + dedup + ' ta takror olib tashlandi' : '') + (removed ? ', ' + removed + ' ta nofaol/yo\'q guruh o\'chirildi' : '') + (blocksMerged ? ', ' + blocksMerged + ' ta blok birlashtirildi' : ''));
                }
            }).fail(function(xhr) {
                $st.css('color', '#dc2626').text(xhr.status === 419
                    ? 'Sessiya eskirgan. Sahifani yangilang (Ctrl+Shift+R) va qayta urinib ko\'ring.'
                    : ('Yangi guruhlarni yuklab bo\'lmadi (HTTP ' + xhr.status + ').'));
            }).always(function() { $btn.prop('disabled', false).css('opacity', 1); });
        }

        // Nofaol yoki HEMISda (bazada) yo'q guruhlarni ekrandan olib tashlash — server bilan ID bo'yicha
        // tekshiriladi (filtrga bog'liq emas). ID'siz qatorlar (bashoratdagi soxta guruhlar) ham olib tashlanadi.
        function mnRemoveMissingGroups(done) {
            var rows = [], names = [], removedNames = [];
            (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                (oq.rows || []).forEach(function(r) {
                    if (+r.gid > 0) rows.push({ gid: +r.gid, count: +r.count || 0, name: r.name });
                    else if (!(r.gids && r.gids.length)) names.push(r.name);
                });
            }); }); });
            // badIds — nofaol / bazada yo'q IDlar; lookup — ID'siz qatorlar uchun nom bo'yicha natija.
            // HECH NARSA avtomatik o'chirilmaydi: avval nomzodlar yig'iladi, foydalanuvchi tasdiqlasa o'chadi.
            var apply = function(badIds, lookup) {
                var cand = []; // {row, why}
                var candNorm = {};
                (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                    (oq.rows || []).forEach(function(r) {
                        var noId = !(+r.gid > 0) && !(r.gids && r.gids.length);
                        if (noId) {
                            var f = lookup ? lookup[r.name] : undefined;
                            if (f && f.gid && f.active) { r.gid = f.gid; r.count = f.count; r._db = 'ok'; manualKnownIds[f.gid] = true; return; } // bazada bor — ID biriktiramiz
                            if (f === undefined) return; // server tekshira olmadi — tegmaymiz
                            r._db = (f && !f.active) ? 'inactive' : 'missing';
                            if (f && f.gid) r.gid = f.gid; // nofaol bo'lsa ham ID ko'rinib tursin
                            cand.push({ row: r, why: f && !f.active ? 'nofaol' : 'bazada yo\'q (bashorat/soxta?)' });
                            candNorm[mnNormName(r.name)] = true;
                            return;
                        }
                        if (+r.gid > 0) {
                            r._db = badIds[+r.gid] ? (badIds[+r.gid] === 'inactive' ? 'inactive' : 'missing') : 'ok';
                            if (badIds[+r.gid]) { cand.push({ row: r, why: badIds[+r.gid] === 'inactive' ? 'nofaol' : 'bazada yo\'q' }); candNorm[mnNormName(r.name)] = true; }
                        }
                    });
                }); }); });
                // Nomi nofaol/yo'q guruh bilan bir xil, talabasi yo'q (0) boshqa qatorlar — takror nusxalar, ular ham nomzod
                (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                    (oq.rows || []).forEach(function(r) {
                        if (cand.some(function(c) { return c.row === r; })) return;
                        if (!(+r.count > 0) && candNorm[mnNormName(r.name)]) cand.push({ row: r, why: 'nomi nofaol/yo\'q guruh bilan bir xil, 0 talaba (takror' + (+r.gid > 0 ? ', bazada #' + r.gid + ' ' + (r._db === 'ok' ? 'faol' : r._db || '?') : ', ID yo\'q') + ')' });
                    });
                }); }); });
                if (!cand.length) { done([]); return; }
                var withStudents = cand.filter(function(c) { return +c.row.count > 0; }).length;
                var list = cand.slice(0, 40).map(function(c) { return '• ' + (c.row.hemis_name || c.row.name) + (+c.row.gid > 0 ? ' #' + c.row.gid : '') + ' — ' + c.why + (+c.row.count > 0 ? ' (' + c.row.count + ' talaba!)' : ''); }).join('\n');
                var ok = confirm(cand.length + ' ta guruh HEMISda nofaol yoki bazada yo\'q' + (withStudents ? ' (shundan ' + withStudents + ' tasida talaba bor!)' : '') + '.\n\n' + list + (cand.length > 40 ? '\n...' : '') +
                                 '\n\nRo\'yxatdan OLIB TASHLANSINMI? ("Bekor qilish" — hech narsa o\'chirilmaydi, joylashuv saqlanadi)');
                if (!ok) { done([]); return; }
                var rm = new Set(cand.map(function(c) { return c.row; }));
                (afterState || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) {
                    (co.oqims || []).forEach(function(oq) {
                        oq.rows = (oq.rows || []).filter(function(r) { if (rm.has(r)) { removedNames.push(r.hemis_name || r.name); return false; } return true; });
                    });
                    co.oqims = (co.oqims || []).filter(function(oq) { return (oq.rows || []).length > 0; });
                }); });
                afterState = (afterState || []).filter(function(bl) { return (bl.courses || []).some(function(co) { return (co.oqims || []).length > 0; }); });
                done(removedNames);
            };
            if (!rows.length && !names.length) { apply({}, null); return; }
            $.ajax({ url: SCREEN_DIFF_URL, method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, contentType: 'application/json', data: JSON.stringify({ rows: rows, names: names }) })
                .done(function(r) {
                    var bad = {};
                    (r.diff || []).forEach(function(d) { if (!d.group_in_db) bad[d.gid] = 'missing'; else if (d.group_active === false) bad[d.gid] = 'inactive'; });
                    apply(bad, r.name_lookup || {});
                })
                .fail(function() { apply({}, null); }); // server javob bermasa hech narsa o'chirilmaydi
        }

        // Fon rejimidagi talabalar importi holatini kuzatish — tugagach sonlar avtomatik yangilanadi
        var HEMIS_STATUS_URL = '{{ route("admin.reports.oqim.hemis.status") }}';
        var stPollTimer = null, stLastState = null;
        function stText(st) {
            if (st.state === 'queued')  return '⏳ Talabalar importi navbatda (' + (st.queued_at || '') + (st.by ? ', ' + st.by : '') + ')... Navbat ishchisi (queue worker) ishlayotgan bo\'lishi kerak.';
            if (st.state === 'running') return '🔄 Talabalar importi bajarilmoqda (boshlandi ' + (st.started_at || '') + ')... Tugagach sonlar o\'zi yangilanadi.';
            if (st.state === 'done')    return '✓ Talabalar importi tugadi ' + (st.finished_at || '') + (st.imported != null ? ' — ' + st.imported + ' ta talaba' : '') + '.';
            if (st.state === 'failed')  return '✗ Talabalar importi xato bilan tugadi: ' + (st.error || '') + '.';
            if (st.state === 'stale')   return '⚠ Talabalar importi ' + (st.queued_at || '') + ' da navbatga qo\'yilgan, lekin 2 soatdan beri bajarilmadi (navbat ishchisi ishlamayotgan bo\'lishi mumkin). Qayta urinish mumkin.';
            return '';
        }
        function pollStudentImport(auto) {
            $.get(HEMIS_STATUS_URL).done(function(st) {
                var txt = stText(st);
                if (st.state === 'queued' || st.state === 'running') {
                    $('#mn-hemis-status').css('color', '#0369a1').text(txt);
                    $('#mn-hemis-students').prop('disabled', true).css('opacity', 0.6);
                    stLastState = st.state;
                    stPollTimer = setTimeout(function() { pollStudentImport(true); }, 10000);
                    return;
                }
                $('#mn-hemis-students').prop('disabled', false).css('opacity', 1);
                if (st.state === 'done' && (stLastState === 'running' || stLastState === 'queued')) {
                    // Biz kuzatgan import hozir tugadi — ekrandagi sonlarni avtomatik yangilaymiz
                    $('#mn-hemis-status').css('color', '#16a34a').text(txt + ' Ekran yangilanmoqda...');
                    stLastState = null;
                    if (afterState && afterState.length) mergeNewGroups(); else $('#mn-hemis-status').css('color', '#16a34a').text(txt);
                } else if (!auto && txt) {
                    $('#mn-hemis-status').css('color', st.state === 'failed' ? '#dc2626' : (st.state === 'stale' ? '#b45309' : '#64748b')).text(txt + (st.students_updated ? ' (bazada oxirgi yangilanish: ' + st.students_updated + ')' : ''));
                } else if (st.state === 'failed' || st.state === 'stale') {
                    $('#mn-hemis-status').css('color', st.state === 'failed' ? '#dc2626' : '#b45309').text(txt);
                }
                stLastState = null;
            });
        }

        // Guruh tortish rejimini tanlash: joylashuvni SAQLAB yangilarini qo'shish yoki HAMMASINI qayta joylash
        function openGroupPullChoice() {
            var hasLayout = afterState && afterState.length;
            var $ov = $('#gp-overlay');
            if (!$ov.length) {
                $ov = $('<div id="gp-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;padding:20px;">' +
                    '<div style="background:#fff;border-radius:14px;width:100%;max-width:560px;box-shadow:0 24px 60px rgba(0,0,0,.35);overflow:hidden;">' +
                    '<div style="padding:14px 18px;background:linear-gradient(135deg,#0f766e,#14b8a6);color:#fff;font-weight:800;font-size:15px;">⇩ Guruhlarni HEMISdan tortish</div>' +
                    '<div id="gp-body" style="padding:14px 18px;display:flex;flex-direction:column;gap:10px;"></div>' +
                    '<div style="display:flex;justify-content:flex-end;padding:10px 18px;border-top:1px solid #f1f5f9;background:#fbfdff;"><button type="button" class="af-btn af-draft" onclick="$(\'#gp-overlay\').hide()">Bekor qilish</button></div></div></div>').appendTo('body');
            }
            var h = '<div style="font-size:12.5px;color:#475569;">HEMISdan barcha faol guruhlar ro\'yxati tortiladi (bazaga yoziladi). Keyin ekran bilan nima qilinsin?</div>';
            h += '<button type="button" class="gp-opt" onclick="startGroupPull(\'merge\')"' + (hasLayout ? '' : ' disabled style="opacity:.5"') + '>' +
                 '<b>🧩 Joylashuvni SAQLAB: yangilarini qo\'shish, nofaol/yo\'q guruhlarni olib tashlash</b><small>Ekrandagi (qoralamadagi) oqimlar tartibi o\'zgarmaydi. Yangi guruhlar "Yangi (HEMIS)" oqimiga tushadi, talaba sonlari bazadan yangilanadi, HEMISda nofaol yoki yo\'q guruhlar (va ID\'siz soxta qatorlar) ro\'yxatdan olib tashlanadi, bir xil fakultet/yo\'nalishdagi takror bloklar birlashtiriladi.' + (hasLayout ? '' : ' (Ekranda joylashuv yo\'q)') + '</small></button>';
            h += '<button type="button" class="gp-opt" onclick="startGroupPull(\'replace\')">' +
                 '<b>🔄 HAMMASINI HEMIS bo\'yicha qayta joylash</b><small>Ekrandagi joylashuv almashtiriladi: barcha faol guruhlar fakultet → kurs → til bo\'yicha standart oqimlarga joylanadi (nomlar HEMISdagidek). Oldingi holat "↶ Bekor qilish" bilan qaytadi; bazadagi saqlangan qoralamaga tegilmaydi.</small></button>';
            $('#gp-body').html(h);
            $ov.css('display', 'flex');
        }
        var GP_MODE = 'replace';
        function startGroupPull(mode) {
            GP_MODE = mode;
            $('#gp-overlay').hide();
            $('#mn-hemis-groups').prop('disabled', true).css('opacity', 0.6);
            pullGroupsPage(1, { imported: 0, created: 0, updated: 0 });
        }

        // Guruhlarni HEMISdan SAHIFAMA-SAHIFA tortish (har so'rov qisqa — vaqt limitiga tushmaydi)
        function pullGroupsPage(page, acc) {
            var $btn = $('#mn-hemis-groups');
            $('#mn-hemis-status').css('color', '#0369a1').text('Guruhlar HEMISdan tortilmoqda: sahifa ' + page + (acc.pageCount ? '/' + acc.pageCount : '') + ' ... (' + acc.imported + ' ta)');
            $.ajax({ url: HEMIS_PULL_URL, method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, data: { what: 'groups', page: page } })
                .done(function(res) {
                    acc.imported += (+res.imported || 0); acc.created += (+res.created || 0); acc.updated += (+res.updated || 0);
                    acc.inactive = (acc.inactive || 0) + (+res.inactive_in_page || 0); acc.hasActive = acc.hasActive || !!res.has_active_field;
                    acc.pageCount = res.pageCount;
                    if (!res.done && res.page < res.pageCount && page < 200) { pullGroupsPage(page + 1, acc); return; }
                    $btn.prop('disabled', false).css('opacity', 1);
                    var extra = (res.groups_total ? ' Bazada ' + res.groups_total + ' ta guruh.' : '')
                              + (acc.hasActive ? ' HEMIS javobida "active" maydoni bor (nofaol: ' + acc.inactive + ').' : ' HEMIS javobida "active" maydoni YO\'Q — nofaollik faqat ro\'yxatda ko\'rinmaslik bo\'yicha aniqlanadi.');
                    MN_PULL_NOTE = '[HEMIS tortish: ' + acc.imported + ' ta guruh, yangi ' + acc.created + (res.deactivated ? ', ko\'rinmagani uchun nofaol qilindi ' + res.deactivated : '') + '; ' + (acc.hasActive ? '"active" maydoni BOR, nofaol: ' + acc.inactive : '"active" maydoni YO\'Q') + ']';
                    $('#mn-hemis-status').css('color', '#16a34a').text('✓ HEMISdan ' + acc.imported + ' ta guruh tortildi (yangi: ' + acc.created + ', yangilangan: ' + acc.updated + (acc.hasActive ? ', HEMISda nofaol: ' + acc.inactive : '') + (res.deactivated ? ', HEMISda ko\'rinmagani uchun nofaol qilindi: ' + res.deactivated : '') + ').' + extra);
                    if (GP_MODE === 'merge' && afterState && afterState.length) mergeNewGroups(); // joylashuv saqlanadi
                    else loadFromHemis(acc); // hammasi HEMIS bo'yicha qayta joylanadi
                })
                .fail(function(xhr) {
                    $btn.prop('disabled', false).css('opacity', 1);
                    var rj = xhr.responseJSON || {};
                    var msg = rj.error || (rj.message ? 'Server xatosi: ' + rj.message + (rj.file ? ' (' + String(rj.file).split('/').pop() + ':' + rj.line + ')' : '') : null)
                           || ('Xatolik (HTTP ' + xhr.status + ') — sahifa ' + page + '. Server javobi JSON emas.');
                    // Javob JSON bo'lmasa — fatal xato bo'lishi mumkin; uni statusdan olamiz
                    $.get(HEMIS_STATUS_URL).done(function(st) {
                        var f = st.groups_pull_fatal;
                        if (f && f.message) msg += ' | FATAL: ' + f.message + ' (' + f.file + ':' + f.line + ', ' + f.at + ')';
                        if (st.php) msg += ' | PHP ' + st.php.version + ', max_execution_time=' + st.php.max_execution_time + ', memory_limit=' + st.php.memory_limit + (st.php.set_time_limit ? '' : ', set_time_limit O\'CHIQ');
                        $('#mn-hemis-status').css('color', '#dc2626').text(msg + (acc.imported ? ' (' + acc.imported + ' ta guruh yozilib ulgurdi)' : ''));
                    }).fail(function() {
                        $('#mn-hemis-status').css('color', '#dc2626').text(msg);
                    });
                });
        }

        // HEMISdan guruh/talabalarni tortish (guruhlar — sahifama-sahifa sinxron, talabalar — fon rejimida)
        function hemisPull(what) {
            var label = what === 'groups' ? 'Guruhlar' : 'Talabalar';
            if (what === 'groups') { openGroupPullChoice(); return; }
            if (!confirm("Talabalar HEMISdan tortilsinmi? Jarayon fon rejimida ishlaydi va bir necha daqiqa davom etishi mumkin.")) return;
            var $btn = $('#mn-hemis-students').prop('disabled', true).css('opacity', 0.6);
            $('#mn-hemis-status').css('color', '#0369a1').text(what === 'groups' ? "Guruhlar HEMISdan tortilmoqda, kuting..." : "So'rov yuborilmoqda...");
            $.ajax({ url: HEMIS_PULL_URL, method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }, data: { what: what } })
                .done(function(res) {
                    var extra = res.groups_total
                        ? ' Bazada ' + res.groups_total + ' ta guruh' + (res.groups_updated ? ' (oxirgi yangilanish: ' + res.groups_updated + ')' : '') + '.'
                        : '';
                    $('#mn-hemis-status').css('color', '#16a34a').text('✓ ' + (res.message || 'Boshlandi.') + extra);
                    // Guruhlar sinxron tortildi — HEMISdagi barcha faol guruhlarni fakultet/kurs/til bo'yicha ekranga yuklaymiz
                    if (what === 'groups' && res.sync) loadFromHemis(res);
                    // Talabalar — fon rejimida: holatini kuzatamiz, tugagach sonlar yangilanadi
                    if (what === 'students') { stLastState = 'queued'; clearTimeout(stPollTimer); stPollTimer = setTimeout(function() { pollStudentImport(true); }, 3000); }
                })
                .fail(function(xhr) {
                    // 419 — sessiya (CSRF token) eskirgan: uzoq ochiq turgan sahifada
                    // yoki server keshi tozalangandan keyin chiqadi. Foydalanuvchi
                    // "HTTP 419" dan nima qilishni bilmaydi — aniq yo'l ko'rsatiladi.
                    var msg = xhr.status === 419
                        ? 'Sessiya eskirgan. Sahifani yangilang (Ctrl+Shift+R) va qayta urinib ko\'ring.'
                        : ((xhr.responseJSON && xhr.responseJSON.error)
                            ? xhr.responseJSON.error
                            : ('Xatolik (HTTP ' + xhr.status + ')'));
                    $('#mn-hemis-status').css('color', '#dc2626').text(msg);
                })
                .always(function() { $btn.prop('disabled', false).css('opacity', 1); });
        }

        // ===== Tasdiqlangan oqim (asosiy ekran) — oxirgi tasdiqlangan holat va sanalar bo'yicha versiyalar =====
        var AP_LOADED = false;      // tasdiqlangan versiya ekranga yuklanganmi
        var AP_VERSIONS = [];       // versiyalar ro'yxati (sana bo'yicha kamayish tartibida)
        var AP_CURRENT = null;      // hozir ekranda turgan versiya (to'liq: blocks, context...)
        var manualContext = null;   // qo'lda tuzatish qaysi kontekst ostida tasdiqlanadi (null — joriy filtrlar)
        var manualKnownIds = {};    // ekrandagi holat hisobga olgan HEMIS guruh IDlari (yangi guruhlarni aniqlash uchun)
        // Joriy o'quv yili ("2026-2027"): iyuldan boshlab shu yil
        function currentAcademicYear() { var d = new Date(); var y = d.getMonth() + 1 >= 7 ? d.getFullYear() : d.getFullYear() - 1; return y + '-' + (y + 1); }
        // Versiya konteksti: REJA bo'lib, uning o'quv yili allaqachon boshlangan bo'lsa — endi bu REAL holat.
        // (Aks holda yangilash/saqlash "reja" rejimida talabalarni +1 kursga surib, guruhlarni noto'g'ri kursga qo'yadi.)
        // Joriy filtrlardan REAL (reja emas) saqlash konteksti
        function realContextFromFilters() { var c = getFilters(true); delete c.projection; delete c.academic_year; return c; }
        function normalizeManualContext(ctx) {
            if (!ctx || !Object.keys(ctx).length) return null;
            var c = $.extend(true, {}, ctx);
            if (+c.projection && String(c.academic_year || '') <= currentAcademicYear()) { c.projection = 0; c.academic_year = ''; c._was_plan = 1; }
            return c;
        }
        var calcGroupIds = [];      // oxirgi hisoblashga kirgan guruh IDlari (joriy va optimizatsiya — bir xil to'plam)

        // Bloklardagi qatorlardan guruh IDlarini yig'adi (gid — haqiqiy guruh, gids — birlashtirilgan manba guruhlar)
        function idsFromBlocks(blocks) {
            var set = {};
            (blocks || []).forEach(function(bl) { (bl.courses || []).forEach(function(co) { (co.oqims || []).forEach(function(oq) {
                (oq.rows || []).forEach(function(r) {
                    if (+r.gid > 0) set[+r.gid] = true;
                    (r.gids || []).forEach(function(g) { if (+g > 0) set[+g] = true; });
                });
            }); }); });
            return set;
        }
        function idSetFromList(list) { var set = {}; (list || []).forEach(function(g) { if (+g > 0) set[+g] = true; }); return set; }

        function apVersionLabel(r) {
            var s = r.summary || {};
            return (r.approved_at || '—') + ' · ' + (r.kind === 'plan' ? 'Reja ' + (r.academic_year || '') : 'Real')
                + ' · ' + (r.faculty_name || '') + (r.approver ? ' · ' + r.approver : '')
                + ' · ' + (s.students || 0) + ' talaba / ' + (s.oqim || 0) + ' oqim';
        }

        // Versiyalar ro'yxatini yuklaydi; selectLatest=true bo'lsa eng oxirgisini ekranga chiqaradi
        function loadApprovedList(selectLatest) {
            var dekanFaculty = document.getElementById('dekan_faculty_id');
            var p = {};
            if (dekanFaculty) p.faculty = dekanFaculty.value;
            $.get(HISTORY_URL, p).done(function(rows) {
                AP_VERSIONS = rows || [];
                var $sel = $('#ap-version').empty();
                $('#ap-tab-badge').toggle(AP_VERSIONS.length > 0).text(AP_VERSIONS.length);
                if (!AP_VERSIONS.length) {
                    $sel.append('<option value="">Hali tasdiqlangan oqim yo\'q</option>');
                    $('#ap-badge, #ap-edit, #ap-excel, #ap-note').hide();
                    $('#ap-body').html('<div style="padding:48px 20px;text-align:center;color:#94a3b8;font-size:14px;font-weight:600;line-height:1.7;">' +
                        'Hali tasdiqlangan oqim yo\'q.<br><span style="font-size:12.5px;font-weight:500;">"Hisoblash" → "🖐 Qo\'lda tuzatish" → "✓ Tasdiqlash" — tasdiqlangan holat sana-vaqti bilan shu yerda saqlanadi.</span></div>');
                    $('#empty-state').hide();
                    $('#table-area').show();
                    if (!AP_LOADED) switchTab('approved');
                    return;
                }
                AP_VERSIONS.forEach(function(r) {
                    $sel.append($('<option>').val(r.id).text(apVersionLabel(r)));
                });
                var keepId = AP_CURRENT ? AP_CURRENT.id : null;
                var pick = (selectLatest || !keepId || !AP_VERSIONS.some(function(r){ return r.id === keepId; }))
                    ? AP_VERSIONS[0].id : keepId;
                $sel.val(pick);
                selectVersion(pick);
            }).fail(function() {
                $('#ap-version').empty().append('<option value="">Tarixni yuklab bo\'lmadi</option>');
            });
        }

        // Tanlangan sanadagi versiyani ekranga chiqaradi (faqat ko'rish; tahrir uchun "Qo'lda tuzatish")
        function selectVersion(id) {
            if (!id) return;
            $('#ap-body').html('<div style="padding:30px;text-align:center;color:#94a3b8;">Yuklanmoqda...</div>');
            $.get(HISTORY_SHOW_URL + '/' + id).done(function(res) {
                AP_CURRENT = res;
                AP_LOADED = true;
                var grand = renderBlocks(res.blocks || [], '#ap-body', false);
                $('#ap-badge').css('display', 'inline-block').text(
                    '✓ Tasdiqlangan · ' + (res.approved_at || '') + (res.approver ? ' · ' + res.approver : '') +
                    ' · ' + (res.kind === 'plan' ? 'REJA ' + (res.academic_year || '') : 'Real') + ' · Jami talaba: ' + grand + ' ta');
                $('#ap-note').toggle(!!res.note).text(res.note ? 'Izoh: ' + res.note : '');
                $('#ap-edit').toggle(!!CAN_APPROVE);
                $('#ap-excel').show();
                $('#empty-state').hide();
                $('#table-area').show();
                if (activeTab !== 'manual') switchTab('approved');
            }).fail(function(xhr) {
                $('#ap-body').html('<div style="padding:30px;text-align:center;color:#dc2626;">Versiyani yuklab bo\'lmadi' + (xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '.</div>');
            });
        }

        // ===== Saqlangan qoralamalar (tasdiqlanmagan ish) =====
        var DRAFTS_URL = '{{ route("admin.reports.oqim.drafts") }}';
        var AP_DRAFTS = [];
        function loadDraftsList() {
            $.get(DRAFTS_URL).done(function(rows) {
                AP_DRAFTS = (rows || []).filter(function(r) { return r.has_data; });
                var $sel = $('#ap-draft').empty();
                if (!AP_DRAFTS.length) { $('#ap-drafts').hide(); return; }
                AP_DRAFTS.forEach(function(r) {
                    var st = r.status === 'approved' ? 'tasdiqlangan holat' : 'QORALAMA';
                    var s2 = r.summary || {};
                    $sel.append($('<option>').val(r.id).text(
                        (r.updated_at || '') + ' · ' + st + ' · ' + (r.kind === 'plan' ? 'Reja ' + (r.academic_year || '') : 'Real')
                        + ' · ' + (r.faculty_name || '') + (r.creator ? ' · ' + r.creator : '')
                        + ' · ' + (s2.students || 0) + ' talaba / ' + (s2.oqim || 0) + ' oqim'));
                });
                $('#ap-drafts').css('display', 'flex');
            });
        }
        function openDraft() {
            var id = $('#ap-draft').val();
            if (!id) { mnFlash('Qoralamani tanlang.'); return; }
            $.get(SNAP_SHOW_URL, { id: id }).done(function(res) {
                if (!res || !res.found || !res.data) { mnFlash('Qoralama topilmadi.'); return; }
                afterState = res.data;
                manualContext = normalizeManualContext(res.context);
                manualKnownIds = idsFromBlocks(afterState);
                MN_UNDO = [];
                mnRecalc();
                renderAfterBody();
                switchTab('manual');
                mergeNewGroups({ countsOnly: true, silent: true }); // sonlar bazadan avtomatik
                $('#mn-save-status').css('color', '#92400e').text((res.status === 'approved' ? 'Tasdiqlangan holat' : 'Qoralama') + ' (' + (res.updated_at || '') + ') yuklandi — davom ettiring; "💾 Qoralama saqlash" shu qoralamani yangilaydi, "✓ Tasdiqlash" tarixga yozadi.');
            }).fail(function(xhr) { mnFlash('Qoralamani yuklab bo\'lmadi (HTTP ' + xhr.status + ').'); });
        }

        // Ekrandagi versiyani qo'lda tuzatishga (drag & drop) yuklaydi — tasdiqlansa yangi sana bilan tarixga tushadi
        function editVersion() {
            if (!AP_CURRENT || !AP_CURRENT.blocks) { mnFlash('Avval versiyani tanlang.'); return; }
            afterState = JSON.parse(JSON.stringify(AP_CURRENT.blocks));
            manualContext = normalizeManualContext(AP_CURRENT.context);
            manualKnownIds = idsFromBlocks(afterState);
            MN_UNDO = [];
            mnRecalc();
            renderAfterBody();
            switchTab('manual');
            $('#mn-save-status').css('color', '#166534').text('Tasdiqlangan versiya (' + (AP_CURRENT.approved_at || '') + ') yuklandi — tuzating va "✓ Tasdiqlash" bosing, yangi sana bilan tarixga tushadi.');
            mergeNewGroups({ countsOnly: true, silent: true }); // sonlar bazadan avtomatik
        }

        function exportVersionExcel() {
            if (!AP_CURRENT) return;
            var p = new URLSearchParams();
            p.set('id', AP_CURRENT.id);
            p.set('format', 'table_xlsx');
            window.location = HISTORY_EXPORT_URL + '?' + p.toString();
        }

        // Tarix oynasidan versiyani asosiy ekranga chiqarish
        function openVersionOnScreen(id) {
            closeHistory();
            if (!AP_VERSIONS.some(function(r){ return r.id === id; })) {
                // Ro'yxatda yo'q (filtr tufayli) — ro'yxatni qayta yuklab, keyin tanlaymiz
                AP_CURRENT = { id: id };
                loadApprovedList(false);
                return;
            }
            $('#ap-version').val(id);
            selectVersion(id);
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
        function exportHistoryTable() {
            if (!histViewId) {
                alert('Avval tarixdagi versiyani Ko\'rish orqali oching.');
                return;
            }
            var p = new URLSearchParams();
            p.set('id', histViewId);
            p.set('format', 'table_xlsx');
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
                        '<td style="padding:6px 8px;white-space:nowrap;"><button type="button" onclick="viewHistory(' + r.id + ')" style="background:#2b5ea7;color:#fff;border:none;border-radius:6px;padding:3px 10px;font-size:12px;cursor:pointer;">Ko\'rish</button> ' +
                        '<button type="button" onclick="openVersionOnScreen(' + r.id + ')" title="Shu versiyani asosiy ekranga chiqarish — ustida amallar bajarish mumkin" style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:3px 10px;font-size:12px;cursor:pointer;">Ekranga</button></td>' +
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

            // Sahifa ochilganda — oxirgi tasdiqlangan oqim darhol ekranda ko'rinsin
            loadApprovedList(true);
            loadDraftsList();
            // Fon rejimidagi talabalar importi ketayotgan bo'lsa — kuzatishni davom ettiramiz
            pollStudentImport(false);

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
        .ap-tab-badge { background:#dcfce7; color:#166534; font-size:10.5px; font-weight:800; padding:1px 7px; border-radius:999px; }
        .oq-tab[data-tab="approved"].active { color:#166534; border-bottom-color:#16a34a; }

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

        /* ===== Qo'lda tuzatish (drag & drop) vkladkasi ===== */
        .mn-block { margin-bottom:26px; }
        .mn-courses { display:flex; gap:12px; flex-wrap:nowrap; overflow-x:auto; align-items:flex-start; padding:12px; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 8px 8px; background:#fbfdff; }
        .mn-course { flex:0 0 auto; width:258px; }
        .mn-course-head { font-size:12px; font-weight:800; color:#1e3a5f; background:linear-gradient(135deg,#dbe4ef,#cbd7e8); border:1px solid #b8c6dc; border-radius:8px; padding:6px 8px; text-align:center; }
        .mn-oqim { border:1px solid #e2e8f0; border-left-width:3px; border-radius:8px; margin-top:6px; background:#fff; overflow:hidden; transition:box-shadow .12s, border-color .12s, background .12s; }
        .mn-oqim.lang-uz  { border-left-color:#3b82f6; }
        .mn-oqim.lang-rus { border-left-color:#f43f5e; }
        .mn-oqim.lang-ing { border-left-color:#8b5cf6; }
        .mn-oqim.mn-over { border-color:#a21caf; box-shadow:0 0 0 2px rgba(162,28,175,0.25); background:#fdf4ff; }
        .mn-oqim-head { display:flex; align-items:center; gap:6px; padding:5px 8px; background:#f0f6ff; border-bottom:1px solid #e2e8f0; }
        .mn-label { width:70px; font-weight:800; font-size:11px; color:#2b5ea7; border:1px solid transparent; background:transparent; border-radius:4px; padding:1px 3px; }
        .mn-label:hover, .mn-label:focus { border-color:#cbd5e1; background:#fff; outline:none; }
        .mn-label-ro { font-weight:800; font-size:11px; color:#2b5ea7; }
        .mn-oqim-total { margin-left:auto; font-size:10.5px; font-weight:800; color:#16a34a; white-space:nowrap; }
        .mn-mixed { font-size:9.5px; font-weight:800; color:#b45309; background:#fef3c7; border-radius:999px; padding:1px 6px; white-space:nowrap; }
        .mn-row { display:flex; align-items:center; gap:6px; padding:3px 8px; border-top:1px solid #f1f5f9; font-size:12px; background:#fff; }
        .mn-row:first-of-type { border-top:none; }
        .mn-row[draggable="true"] { cursor:grab; }
        .mn-row[draggable="true"]:active { cursor:grabbing; }
        .mn-row.mn-dragging { opacity:0.45; }
        .mn-row.lang-rus { background:#fffafa; }
        .mn-row.lang-ing { background:#fbfaff; }
        .mn-handle { color:#cbd5e1; font-size:13px; flex-shrink:0; }
        .mn-row[draggable="true"] .mn-handle { color:#94a3b8; }
        .mn-name { flex:1; min-width:0; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mn-name-chk { cursor:pointer; text-decoration:underline dotted #94a3b8; text-underline-offset:2px; }
        .mn-name-chk:hover { color:#a21caf; }
        .mn-cnt { width:48px; height:22px; border:1px solid #e2e8f0; border-radius:5px; text-align:center; font-size:11.5px; font-weight:700; color:#0f172a; -moz-appearance:textfield; flex-shrink:0; }
        .mn-cnt::-webkit-outer-spin-button, .mn-cnt::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
        .mn-cnt:focus { outline:none; border-color:#a21caf; box-shadow:0 0 0 2px rgba(162,28,175,0.15); }
        .mn-cnt-ro { min-width:40px; text-align:center; font-weight:700; color:#334155; font-size:11.5px; flex-shrink:0; }
        .mn-dbflag { display:inline-block; font-size:9px; font-weight:800; border-radius:999px; padding:0 5px; margin-left:3px; vertical-align:middle; }
        .mn-dbflag-inactive { background:#fee2e2; color:#b91c1c; }
        .mn-dbflag-missing { background:#ffedd5; color:#c2410c; }
        .mn-noid { display:inline-block; margin-left:3px; width:14px; height:14px; line-height:13px; border-radius:50%; background:#fef3c7; color:#b45309; font-size:10px; font-weight:800; text-align:center; cursor:help; }
        .mn-lang { font-size:9.5px; font-weight:800; border-radius:999px; padding:1px 6px; flex-shrink:0; }
        .mn-lang-uz  { color:#1d4ed8; background:#eff6ff; }
        .mn-lang-rus { color:#be123c; background:#fff1f2; }
        .mn-lang-ing { color:#6d28d9; background:#f5f3ff; }
        .mn-x { flex-shrink:0; width:20px; height:20px; line-height:18px; padding:0; border:1px solid #e2e8f0; border-radius:5px; background:#fff; color:#94a3b8; font-size:15px; font-weight:800; cursor:pointer; }
        .mn-mv-sel { z-index:3000; border:1px solid #a21caf; border-radius:6px; font-size:11.5px; padding:3px 4px; background:#fff; box-shadow:0 6px 18px rgba(0,0,0,.18); max-height:60vh; }
        .mn-mv-sel option { padding:3px 6px; }
        .mn-mv-sel optgroup { font-size:11px; color:#a21caf; }
        .mn-row { position:relative; }
        .gp-opt { display:flex; flex-direction:column; gap:3px; text-align:left; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px; background:#fff; cursor:pointer; }
        .gp-opt:hover:not(:disabled) { border-color:#0f766e; background:#f0fdfa; }
        .gp-opt b { font-size:13.5px; color:#0f172a; }
        .gp-opt small { font-size:11.5px; color:#64748b; line-height:1.45; }
        .mn-drop-line { margin:2px 6px; padding:4px 8px; border:2px dashed #a21caf; border-radius:6px; background:#fdf4ff; color:#a21caf; font-size:11px; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; pointer-events:none; }
        .mn-empty-hint { padding:10px 8px; text-align:center; font-size:11px; font-weight:700; color:#a21caf; background:#fdf4ff; border-top:1px dashed #f0abfc; }
        .mn-x:hover { color:#dc2626; background:#fef2f2; border-color:#fecaca; }
        .mn-x-oqim { margin-left:4px; }
        .mn-new { border:2px dashed #cbd5e1; border-radius:8px; margin-top:6px; padding:10px 8px; text-align:center; font-size:11.5px; font-weight:700; color:#94a3b8; transition:all .12s; cursor:pointer; user-select:none; }
        .mn-new:hover { border-color:#a21caf; color:#a21caf; background:#fdf4ff; }
        .mn-new.mn-over { border-color:#a21caf; color:#a21caf; background:#fdf4ff; }
        #mn-flash { display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:#0f172a; color:#fff; font-size:13px; font-weight:700; padding:10px 18px; border-radius:10px; z-index:2000; box-shadow:0 8px 24px rgba(0,0,0,0.35); max-width:80vw; text-align:center; }
    </style>
</x-app-layout>
