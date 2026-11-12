{{-- Vedomost tekshirish filtrlari (bir-biriga bog'langan) — Vedomost tekshirish va Vedomostlar holati sahifalarida --}}
                <div class="filter-container">
                    <!-- Row 1 -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width:155px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width:100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}">{{ $type->education_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1;min-width:200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="faculty" class="select2" style="width:100%;">
                                @if(!isset($dekanFacultyIds) || empty($dekanFacultyIds))
                                    <option value="">Barchasi</option>
                                @endif
                                @foreach($faculties as $fac)
                                    <option value="{{ $fac->id }}">{{ $fac->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1.5;min-width:240px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty" class="select2" style="width:100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width:145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> YN sanasi (dan)</label>
                            <input type="text" id="yn_date_from" class="date-input" placeholder="kk.oo.yyyy" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width:145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#e11d48;"></span> YN sanasi (gacha)</label>
                            <input type="text" id="yn_date_to" class="date-input" placeholder="kk.oo.yyyy" autocomplete="off" />
                        </div>
                        <div class="filter-item" style="min-width:150px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch active" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                    </div>
                    <!-- Row 2 -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width:130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width:100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width:140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width:100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="flex:1;min-width:190px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#1a3268;"></span> Guruh
                                <span id="groups-count" style="font-weight:400;font-size:10px;margin-left:3px;color:#64748b;"></span>
                            </label>
                            <div class="cb-wrap" id="groups-wrap">
                                <button type="button" class="cb-btn" id="groups-btn" onclick="toggleCb('groups')">
                                    <span id="groups-label" class="cb-label-text">Tanlang...</span>
                                    <svg style="width:12px;height:12px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="cb-panel" id="groups-panel" style="display:none;">
                                    <input type="text" class="cb-search" id="groups-search" placeholder="Qidirish..." oninput="filterCb('groups')">
                                    <label class="cb-all-label"><input type="checkbox" id="groups-all" onchange="toggleAllCb('groups', this)"><span>Barchasi tanlash</span></label>
                                    <div class="cb-list" id="groups-list"></div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-item" style="flex:1;min-width:210px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Kafedra</label>
                            <select id="department" class="select2" style="width:100%;">
                                <option value="">Barchasi</option>
                                @foreach($kafedras as $kaf)
                                    <option value="{{ $kaf->department_id }}">{{ $kaf->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1.5;min-width:240px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#0f172a;"></span> Fan
                                <span id="subjects-count" style="font-weight:400;font-size:10px;margin-left:3px;color:#64748b;"></span>
                            </label>
                            <div class="cb-wrap" id="subjects-wrap">
                                <button type="button" class="cb-btn" id="subjects-btn" onclick="toggleCb('subjects')">
                                    <span id="subjects-label" class="cb-label-text">Tanlang...</span>
                                    <svg style="width:12px;height:12px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="cb-panel" id="subjects-panel" style="display:none;">
                                    <input type="text" class="cb-search" id="subjects-search" placeholder="Qidirish..." oninput="filterCb('subjects')">
                                    <label class="cb-all-label"><input type="checkbox" id="subjects-all" onchange="toggleAllCb('subjects', this)"><span>Barchasi tanlash</span></label>
                                    <div class="cb-list" id="subjects-list"></div>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-end;padding-bottom:2px;gap:8px;">
                            <div id="search-loading" style="display:none;align-items:center;color:#2b5ea7;">
                                <svg class="animate-spin" style="height:16px;width:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity:.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path style="opacity:.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <button type="button" id="btn-search" class="btn-calc" onclick="doSearch()">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Qidirish
                            </button>
                        </div>
                    </div>
                </div>
