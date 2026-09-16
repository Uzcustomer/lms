<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Davomat (beacon)</h2>
    </x-slot>

    <style>
        .att-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; }
        .att-lesson { border:1.5px solid #e2e8f0; border-radius:12px; padding:14px 16px; background:#fff; }
        .att-lesson.open { border-color:#0d9488; box-shadow:0 0 0 3px rgba(13,148,136,.12); }
        .att-chip { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
        .att-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 14px; border-radius:10px; font-size:13px; font-weight:700; border:1px solid transparent; cursor:pointer; white-space:nowrap; }
        .att-btn:disabled { opacity:.5; cursor:not-allowed; }
        .att-btn-primary { background:#1e3a8a; color:#fff; }
        .att-btn-teal { background:#0d9488; color:#fff; }
        .att-btn-outline { background:#fff; color:#0f172a; border-color:#cbd5e1; }
        .att-btn-ghost { background:rgba(255,255,255,.14); color:#fff; border-color:rgba(255,255,255,.35); }
        .att-btn-danger { background:#be123c; color:#fff; }
        .att-sess { display:flex; align-items:center; gap:10px; padding:8px 12px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; cursor:pointer; font-size:12.5px; }
        .att-sess:hover { background:#eff6ff; }
        .att-sess.open { border-color:#0d9488; background:#f0fdfa; }
        .att-student { display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; }
        .att-student.present { border-color:#86efac; background:#f0fdf4; }
        .att-student.absent { border-color:#fecaca; background:#fef2f2; }
        .att-student.pending.seen { border-color:#fcd34d; background:#fffbeb; }
        .att-status { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:800; }
        .att-seg { display:inline-flex; border:1px solid #cbd5e1; border-radius:9px; overflow:hidden; flex-shrink:0; }
        .att-seg button { padding:6px 10px; font-size:11.5px; font-weight:800; background:#fff; color:#64748b; border:0; cursor:pointer; }
        .att-seg button + button { border-left:1px solid #cbd5e1; }
        .att-seg button.on-present { background:#047857; color:#fff; }
        .att-seg button.on-absent { background:#be123c; color:#fff; }
        .att-seg button:disabled { cursor:not-allowed; opacity:.6; }
        .att-counter { font-size:30px; font-weight:800; line-height:1; font-variant-numeric:tabular-nums; }
        .att-big .att-counter { font-size:46px; }
        .att-big .att-student { padding:14px 16px; }
        .att-big .att-student .att-name { font-size:17px; }
        .att-big .att-seg button { padding:9px 14px; font-size:13px; }
        .att-filter { padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid #cbd5e1; background:#fff; cursor:pointer; }
        .att-filter.active { background:#0f172a; color:#fff; border-color:#0f172a; }
        .att-progress { height:8px; border-radius:999px; background:rgba(255,255,255,.25); overflow:hidden; }
        .att-progress > div { height:100%; background:#86efac; transition:width .4s; }
        .att-group-head { display:flex; align-items:center; gap:10px; margin:14px 0 6px; font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
        .att-group-head:first-child { margin-top:0; }
        .att-group-head .line { flex:1; height:1px; background:#e2e8f0; }
        [x-cloak] { display:none !important; }
    </style>

    <div class="py-4" x-data="attendancePage()" x-init="init()" :class="big ? 'att-big' : ''">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Admin: teacher picker --}}
            @if($canPickTeacher)
            <div class="att-card p-4 mb-4" x-show="!(session && big)">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-sm text-gray-600">O'qituvchi:</div>
                    <template x-if="teacher">
                        <div class="font-bold text-gray-900" x-text="teacher.full_name"></div>
                    </template>
                    <template x-if="!teacher">
                        <div class="text-amber-700 text-sm font-semibold">Tanlanmagan — davomat ochish uchun o'qituvchini tanlang</div>
                    </template>
                    <div class="relative ml-auto w-full sm:w-80">
                        <input type="text" x-model="teacherQuery" @input.debounce.300ms="searchTeachers()" @focus="searchTeachers()"
                               placeholder="O'qituvchi ismini yozing…"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <div x-show="teacherResults.length" x-cloak @click.outside="teacherResults = []"
                             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                            <template x-for="t in teacherResults" :key="t.id">
                                <button type="button" @click="pickTeacher(t)"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 border-b border-gray-100">
                                    <div class="font-semibold text-gray-900" x-text="t.full_name"></div>
                                    <div class="text-xs text-gray-500" x-text="t.department || ''"></div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ═══ Lessons (hidden while a session is on the big screen) ═══ --}}
            <div x-show="!session" x-cloak>
                <div class="att-card p-4">
                    <div class="flex items-center justify-between mb-4">
                        <button class="att-btn att-btn-outline" @click="shiftDate(-1)">‹ Oldingi kun</button>
                        <div class="text-center">
                            <div class="font-bold text-gray-900 text-lg" x-text="dateLabel()"></div>
                            <button x-show="date !== today" x-cloak class="text-xs text-blue-700 font-semibold" @click="date = today; loadLessons()">Bugunga qaytish</button>
                        </div>
                        <button class="att-btn att-btn-outline" @click="shiftDate(1)">Keyingi kun ›</button>
                    </div>

                    <div x-show="loadingLessons" class="text-center text-gray-500 text-sm py-8">Yuklanmoqda…</div>
                    <div x-show="!loadingLessons && lessonsError" x-cloak class="text-center text-red-600 text-sm py-6" x-text="lessonsError"></div>
                    <div x-show="!loadingLessons && !lessonsError && lessons.length === 0" x-cloak class="text-center text-gray-500 text-sm py-8">
                        Bu kunda jadvalda dars yo'q.
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <template x-for="l in lessons" :key="lessonKey(l)">
                            <div class="att-lesson" :class="l.session && l.session.status === 'open' ? 'open' : ''">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="att-chip" style="background:#ccfbf1;color:#0f766e;" x-text="l.start_time + '–' + l.end_time"></span>
                                    <span class="text-xs text-gray-500" x-text="l.training_type_name || ''"></span>
                                    <span x-show="!l.has_beacon" x-cloak class="att-chip ml-auto" style="background:#fef3c7;color:#b45309;" title="Bu xonada beacon sozlanmagan">beacon yo'q</span>
                                </div>
                                <div class="font-bold text-gray-900 text-base" x-text="l.subject_name"></div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span x-text="(l.auditorium_name || '—') + ' · ' + l.students_count + ' talaba'"></span>
                                </div>
                                <div class="text-xs text-gray-500" x-text="(l.group_names || []).join(', ')"></div>

                                {{-- Sessions already opened for this slot --}}
                                <div class="mt-3 space-y-1.5" x-show="(l.sessions || []).length">
                                    <template x-for="s in (l.sessions || [])" :key="s.id">
                                        <div class="att-sess" :class="s.status === 'open' ? 'open' : ''" @click="openSession(s.id)">
                                            <span class="att-chip" :style="s.status === 'open' ? 'background:#0d9488;color:#fff' : 'background:#e2e8f0;color:#334155'"
                                                  x-text="s.status === 'open' ? 'OCHIQ' : 'yopilgan'"></span>
                                            <span class="text-gray-600" x-text="timeOf(s.opened_at)"></span>
                                            <span class="font-bold text-gray-900" x-text="'keldi ' + s.present + ' / ' + s.total"></span>
                                            <span class="text-gray-500" x-show="s.status !== 'open'" x-text="'· kelmadi ' + s.absent"></span>
                                            <span class="ml-auto text-gray-400">›</span>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-3 flex items-center gap-2" x-show="!l.session || l.session.status !== 'open'">
                                    <select class="border border-gray-300 rounded-lg text-sm px-2 py-2" x-model.number="windowFor[lessonKey(l)]">
                                        <option value="5">5 daq</option>
                                        <option value="10">10 daq</option>
                                        <option value="15">15 daq</option>
                                        <option value="20">20 daq</option>
                                    </select>
                                    <button class="att-btn flex-1" :class="(l.sessions || []).length ? 'att-btn-outline' : 'att-btn-primary'" :disabled="busy" @click="start(l)"
                                            x-text="(l.sessions || []).length ? '+ Yangi davomat ochish' : '▶ Davomatni boshlash'"></button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="text-center text-gray-500 text-sm mt-4">
                    Darsni tanlab <b>Davomatni boshlash</b>ni bosing — talabalar telefondagi ilovada tasdiqlaydi, ro'yxat shu yerda jonli yangilanadi.
                </div>
            </div>

            {{-- ═══ Live session ═══ --}}
            <div x-show="session" x-cloak>
                <div class="rounded-2xl p-5 text-white mb-4" :style="isOpen() ? 'background:linear-gradient(135deg,#0d9488,#1e3a8a)' : 'background:linear-gradient(135deg,#334155,#0f172a)'">
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="flex-1 min-w-[260px]">
                            <div class="text-2xl font-extrabold" x-text="session && session.session.subject_name"></div>
                            <div class="text-sm opacity-90 mt-1" x-text="session && ((session.session.auditorium_name || '—') + ' · ' + (session.session.lesson_pair_name || '') + ' · ' + (session.session.group_names || []).join(', '))"></div>
                            <div x-show="session && !session.session.beacon" x-cloak class="mt-2 text-xs font-semibold" style="color:#fde68a;">Bu xonada beacon yo'q — talabalar tasdiqlay olmaydi, qo'lda belgilang.</div>
                            <div class="att-progress mt-4 max-w-md"><div :style="'width:' + progressPct() + '%'"></div></div>
                            <div class="text-xs opacity-80 mt-1" x-text="session ? session.session.present + ' / ' + session.session.total + ' tasdiqlandi' : ''"></div>
                        </div>
                        <div class="flex gap-7 items-start">
                            <div><div class="att-counter" x-text="session && session.session.present"></div><div class="text-xs opacity-80 mt-1">Keldi</div></div>
                            <div><div class="att-counter opacity-80" x-text="session && session.session.pending"></div><div class="text-xs opacity-80 mt-1">Kutilmoqda</div></div>
                            <div><div class="att-counter" style="color:#fca5a5;" x-text="session && session.session.absent"></div><div class="text-xs opacity-80 mt-1">Kelmadi</div></div>
                            <div class="text-right" x-show="isOpen()">
                                <div class="att-counter" x-text="countdown"></div>
                                <div class="text-xs opacity-80 mt-1">Qoldi</div>
                            </div>
                            <div class="text-right" x-show="!isOpen()">
                                <span class="att-chip" style="background:rgba(255,255,255,.18);color:#fff;">Yopilgan</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <button class="att-btn att-btn-ghost" @click="session = null; big = false; loadLessons()">‹ Darslarga qaytish</button>
                        <button class="att-btn att-btn-ghost" @click="big = !big" x-text="big ? 'Oddiy ko\'rinish' : 'Katta ekran'"></button>
                        <button class="att-btn att-btn-ghost" x-show="isOpen()" :disabled="busy" @click="remind()">🔔 Eslatma yuborish</button>
                        <button class="att-btn att-btn-danger ml-auto" x-show="isOpen()" :disabled="busy" @click="closeSession()">Davomatni yopish</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <template x-for="f in [['all','Hammasi','total'],['present','Keldi','present'],['pending','Kutilmoqda','pending'],['absent','Kelmadi','absent']]" :key="f[0]">
                        <button class="att-filter" :class="filter === f[0] ? 'active' : ''" @click="filter = f[0]"
                                x-text="f[1] + ' (' + (session ? session.session[f[2]] : 0) + ')'"></button>
                    </template>
                    <input type="text" x-model="search" placeholder="Ism bo'yicha qidirish…"
                           class="ml-auto border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-full sm:w-64">
                </div>

                <template x-for="g in grouped()" :key="g.name">
                    <div>
                        <div class="att-group-head" x-show="grouped().length > 1">
                            <span x-text="g.name"></span>
                            <span class="text-gray-400 normal-case tracking-normal" x-text="g.present + ' / ' + g.students.length + ' keldi'"></span>
                            <span class="line"></span>
                        </div>
                        <div class="grid gap-2" :class="big ? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3' : 'grid-cols-1 md:grid-cols-2'">
                            <template x-for="st in g.students" :key="st.student_id">
                                <div class="att-student" :class="st.status + (st.beacon_seen ? ' seen' : '')">
                                    <div class="flex-1 min-w-0">
                                        <div class="att-name font-bold text-gray-900 truncate" x-text="st.full_name"></div>
                                        <div class="att-status mt-1" :style="'color:' + statusColor(st)">
                                            <span x-text="statusIcon(st)"></span>
                                            <span x-text="statusLabel(st)"></span>
                                        </div>
                                    </div>
                                    <div class="att-seg" title="Qo'lda belgilash">
                                        <button :class="st.status === 'present' ? 'on-present' : ''" :disabled="busy || st.status === 'present'" @click="mark(st, 'present')">Keldi</button>
                                        <button :class="st.status === 'absent' ? 'on-absent' : ''" :disabled="busy || st.status === 'absent'" @click="mark(st, 'absent')">Kelmadi</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <div x-show="session && filtered().length === 0" x-cloak class="text-center text-gray-500 text-sm py-10">Bu filtrda talaba yo'q.</div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function attendancePage() {
            const urls = {
                teachers: @json(route('admin.attendance.teachers')),
                selectTeacher: @json(route('admin.attendance.select-teacher')),
                lessons: @json(route('admin.attendance.lessons')),
                start: @json(route('admin.attendance.sessions.start')),
                session: @json(url('/admin/attendance/sessions')),
            };
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            return {
                teacher: @json($teacher ? ['id' => $teacher->id, 'full_name' => $teacher->full_name] : null),
                today: @json($today),
                date: @json($today),
                lessons: [],
                loadingLessons: false,
                lessonsError: null,
                windowFor: {},
                session: null,
                filter: 'all',
                search: '',
                busy: false,
                big: false,
                countdown: '00:00',
                teacherQuery: '',
                teacherResults: [],

                init() {
                    if (this.teacher) this.loadLessons();
                    setInterval(() => this.updateCountdown(), 1000);
                    setInterval(() => { if (this.session && this.isOpen()) this.refreshSession(true); }, 5000);
                },

                async api(url, method = 'GET', body = null) {
                    const res = await fetch(url, {
                        method,
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: body ? JSON.stringify(body) : null,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || 'Xatolik yuz berdi');
                    return data;
                },

                toast(msg, error = false) {
                    const el = document.createElement('div');
                    el.textContent = msg;
                    el.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9999;padding:10px 16px;border-radius:10px;color:#fff;font-weight:600;font-size:14px;box-shadow:0 4px 14px rgba(0,0,0,.2);background:' + (error ? '#be123c' : '#047857');
                    document.body.appendChild(el);
                    setTimeout(() => el.remove(), 3500);
                },

                lessonKey(l) { return l.subject_id + '|' + l.lesson_pair_code + '|' + (l.auditorium_code || ''); },

                timeOf(iso) {
                    if (!iso) return '';
                    const d = new Date(iso);
                    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                },

                dateLabel() {
                    const d = new Date(this.date + 'T00:00:00');
                    const wd = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'][d.getDay()];
                    return wd + ', ' + String(d.getDate()).padStart(2, '0') + '.' + String(d.getMonth() + 1).padStart(2, '0') + '.' + d.getFullYear();
                },

                shiftDate(n) {
                    const d = new Date(this.date + 'T00:00:00');
                    d.setDate(d.getDate() + n);
                    this.date = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                    this.loadLessons();
                },

                async searchTeachers() {
                    try {
                        const data = await this.api(urls.teachers + '?q=' + encodeURIComponent(this.teacherQuery));
                        this.teacherResults = data.data || [];
                    } catch (e) { this.teacherResults = []; }
                },

                async pickTeacher(t) {
                    try {
                        await this.api(urls.selectTeacher, 'POST', { teacher_id: t.id });
                        this.teacher = t;
                        this.teacherResults = [];
                        this.teacherQuery = '';
                        this.session = null;
                        this.loadLessons();
                    } catch (e) { this.toast(e.message, true); }
                },

                async loadLessons() {
                    this.loadingLessons = true;
                    this.lessonsError = null;
                    try {
                        const data = await this.api(urls.lessons + '?date=' + this.date);
                        this.lessons = data.data.lessons || [];
                        for (const l of this.lessons) {
                            const k = this.lessonKey(l);
                            if (!this.windowFor[k]) this.windowFor[k] = 10;
                        }
                    } catch (e) {
                        this.lessonsError = e.message;
                    } finally {
                        this.loadingLessons = false;
                    }
                },

                showSession(data) {
                    this.session = data;
                    this.filter = 'all';
                    this.search = '';
                    this.big = true;
                    this.updateCountdown();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                async start(l) {
                    if (!l.has_beacon && !confirm('Bu xonada beacon sozlanmagan — talabalar tasdiqlay olmaydi, faqat qo\'lda belgilash mumkin. Davom etasizmi?')) return;
                    if ((l.sessions || []).length && !confirm('Bu dars uchun davomat allaqachon o\'tkazilgan. Yangi (qo\'shimcha) davomat ochilsinmi?')) return;
                    this.busy = true;
                    try {
                        const data = await this.api(urls.start, 'POST', {
                            subject_id: l.subject_id,
                            lesson_pair_code: l.lesson_pair_code,
                            date: this.date,
                            window_minutes: this.windowFor[this.lessonKey(l)] || 10,
                        });
                        this.showSession(data.data);
                    } catch (e) { this.toast(e.message, true); }
                    finally { this.busy = false; }
                },

                async openSession(id) {
                    this.busy = true;
                    try {
                        const data = await this.api(urls.session + '/' + id);
                        this.showSession(data.data);
                    } catch (e) { this.toast(e.message, true); }
                    finally { this.busy = false; }
                },

                async refreshSession(silent = false) {
                    if (!this.session) return;
                    try {
                        const data = await this.api(urls.session + '/' + this.session.session.id);
                        this.session = data.data;
                    } catch (e) { if (!silent) this.toast(e.message, true); }
                },

                async mark(st, status) {
                    if (st.status === status) return;
                    this.busy = true;
                    try {
                        const data = await this.api(urls.session + '/' + this.session.session.id + '/mark', 'POST', { student_id: st.student_id, status });
                        this.session = data.data;
                    } catch (e) { this.toast(e.message, true); }
                    finally { this.busy = false; }
                },

                async remind() {
                    this.busy = true;
                    try {
                        const data = await this.api(urls.session + '/' + this.session.session.id + '/remind', 'POST', {});
                        this.session = data.data;
                        this.toast('Xonadagi ' + (data.data.reminded || 0) + ' talabaga eslatma yuborildi.');
                    } catch (e) { this.toast(e.message, true); }
                    finally { this.busy = false; }
                },

                async closeSession() {
                    if (!confirm('Tasdiqlamagan talabalar "kelmadi" deb belgilanadi. Keyin ham qo\'lda o\'zgartira olasiz. Yopilsinmi?')) return;
                    this.busy = true;
                    try {
                        const data = await this.api(urls.session + '/' + this.session.session.id + '/close', 'POST', {});
                        this.session = data.data;
                        this.toast('Davomat yopildi.');
                    } catch (e) { this.toast(e.message, true); }
                    finally { this.busy = false; }
                },

                isOpen() { return !!(this.session && this.session.session.status === 'open'); },

                progressPct() {
                    if (!this.session || !this.session.session.total) return 0;
                    return Math.round(100 * this.session.session.present / this.session.session.total);
                },

                updateCountdown() {
                    if (!this.session || !this.isOpen()) { this.countdown = '00:00'; return; }
                    const left = Math.max(0, Math.floor((new Date(this.session.session.closes_at) - Date.now()) / 1000));
                    this.countdown = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
                    if (left === 0) this.refreshSession(true);
                },

                filtered() {
                    if (!this.session) return [];
                    let list = this.session.students || [];
                    if (this.filter !== 'all') list = list.filter(s => s.status === this.filter);
                    const q = this.search.trim().toLowerCase();
                    if (q) list = list.filter(s => (s.full_name || '').toLowerCase().includes(q));
                    return list;
                },

                grouped() {
                    const map = new Map();
                    for (const s of this.filtered()) {
                        const name = s.group_name || '—';
                        if (!map.has(name)) map.set(name, { name, students: [], present: 0 });
                        const g = map.get(name);
                        g.students.push(s);
                        if (s.status === 'present') g.present++;
                    }
                    return [...map.values()];
                },

                statusColor(st) {
                    if (st.status === 'present') return '#047857';
                    if (st.status === 'absent') return '#be123c';
                    return st.beacon_seen ? '#d97706' : '#64748b';
                },

                statusIcon(st) {
                    if (st.status === 'present') return '✔';
                    if (st.status === 'absent') return '✖';
                    return st.beacon_seen ? '◉' : '○';
                },

                statusLabel(st) {
                    const by = st.decided_by === 'teacher' ? ' · qo\'lda belgilangan' : (st.decided_by === 'system' ? ' · oynada tasdiqlanmadi' : '');
                    if (st.status === 'present') return 'Keldi' + (st.decided_by === 'student' ? ' · telefondan tasdiqladi' : by);
                    if (st.status === 'absent') return 'Kelmadi' + by;
                    return st.beacon_seen ? 'Xonada — hali tasdiqlamadi' : 'Kutilmoqda';
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
